<?php

namespace App\Http\Controllers\API;

use App\Exceptions\FileIsNotCreatedException;
use App\Exceptions\InvalidStateException;
use App\FacebookGroups;
use App\GroupMembers;
use App\Jobs\BuildMembersCSVFile;
use App\Jobs\ManageTagsJob;
use App\Jobs\RemoveMembersJob;
use App\Services\TagService;
use App\Tag;
use Bugsnag\BugsnagLaravel\Facades\Bugsnag;
use Carbon\Carbon;
use Exception;
use Illuminate\Bus\Dispatcher;
use Illuminate\Bus\UniqueLock;
use Illuminate\Container\Container;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response as HttpResponse;
use Symfony\Component\HttpFoundation\Response;

class MemberController extends AbstractApiController
{
    /**
     * @var array The rules used for validation for each action with the action name used as the key
     */
    protected array $ajaxValidatorRules = [
        'removeGroupMembers' => [
            'group_id' => 'required|numeric|exists:facebook_groups,id,deleted_at,NULL',
            'selected_member_ids' => 'required_if:is_multi_page_select_all,false|array',
            'excluded_member_ids' => 'array',
            'is_multi_page_select_all' => 'required|bool',
            'autoResponder' => 'nullable|string',
            'startDate' => 'nullable|string',
            'endDate' => 'nullable|string',
            'tags' => 'nullable|string',
            'searchText' => 'nullable|string',
            'sort' => 'nullable',
        ],
        'update'            => [
            'id'       => 'required|numeric',
            'group_id' => 'required|numeric',
            'email'    => 'nullable|email',
            'lives_in'    => 'nullable|string|max:255',
            'tags_to_add' => 'nullable|array',
            'tags_to_delete' => 'nullable|array',
            'recommended_tags_to_add' => 'nullable|array',
            'recommended_tags_to_delete' => 'nullable|array',
            'agreed_group_rules' => 'integer|in:0,1',
        ],
        'sendToIntegration'  => [
            'group_members_id' => 'required',
        ],
        'bulkManageTags' => [
            'tags_to_add' => 'nullable|array',
            'tags_to_delete' => 'nullable|array',
            'recommended_tags_to_add' => 'nullable|array',
            'recommended_tags_to_delete' => 'nullable|array',
            'group_id' => 'required|numeric|exists:facebook_groups,id',
            'selected_member_ids' => 'required_if:is_multi_page_select_all,false|array',
            'excluded_member_ids' => 'array',
            'is_multi_page_select_all' => 'required|bool',
        ],
        'buildCsv' => [
            'group_id' => 'required|numeric|exists:facebook_groups,id,deleted_at,NULL',
            'selected_member_ids' => 'required_if:is_multi_page_select_all,false|array',
            'excluded_member_ids' => 'nullable|array',
            'is_multi_page_select_all' => 'required|bool',
            'autoResponder' => 'nullable|string',
            'startDate' => 'nullable|string',
            'endDate' => 'nullable|string',
            'tags' => 'nullable|string',
            'searchText' => 'nullable|string',
            'sort' => 'nullable',
        ],
    ];

    /**
     * Sets the middleware for this controller
     *
     * @see SendIntegration
     */
    protected function init()
    {
        $this->middleware('send.integration')->only('sendToIntegration');
    }

    /**
     * Display a listing of the resource.
     *
     * @return HttpResponse with filtered group members
     */
    public function index()
    {
        $group_id = $this->request->group_id;
        $membersFound = 0;
        $query = FacebookGroups::with([
            'members' => function ($membersQuery) use (&$membersFound) {
                /**
                 * @var HasMany $membersQuery
                 */
                if ($this->request->startDate) {
                    // Start date was received converted to the customer time zone.
                    // Convert it back to UTC, as stored in the DB, to match the date_add_time column in query.
                    $startDate = Carbon::parse($this->request->startDate, $this->currentUser->timezone)
                                       ->setTimezone('UTC')
                                       ->format('Y-m-d H:i:s');
                    $membersQuery->where('date_add_time', '>=', $startDate);
                }
                if ($this->request->endDate) {
                    // End date was received converted to the customer time zone.
                    // Convert it back to UTC, as stored in the DB, to match the date_add_time column in query.
                    $endDate = Carbon::parse($this->request->endDate, $this->currentUser->timezone)
                                     ->setTimezone('UTC')
                                     ->add(1, 'day')
                                     ->format('Y-m-d H:i:s');
                    $membersQuery->where('date_add_time', '<', $endDate);
                }

                if ($this->request->tags) {
                    $tagIds = explode(',', $this->request->tags);
                    $membersQuery->whereHas('tags', function ($tagsQuery) use ($tagIds) {
                        $tagsQuery->whereIn('tags.id', $tagIds);
                    }, '=', count($tagIds)); # forces members to have all of tags specified in the filter request
                }

                if (
                    $this->request->autoResponder
                    && !in_array($this->request->autoResponder, ['all', GroupMembers::RESPONSE_STATUS_ERROR])
                ) {
                    $membersQuery->where(
                        'respond_status',
                        GroupMembers::RESPONSE_STATUSES[$this->request->autoResponder]
                    );
                }

                if (
                    $this->request->autoResponder
                    && $this->request->autoResponder === GroupMembers::RESPONSE_STATUS_ERROR
                ) {
                    $membersQuery->whereIn(
                        'respond_status',
                        array_filter(
                            GroupMembers::RESPONSE_STATUSES,
                            function ($responseStatus) {
                                return !in_array($responseStatus, GroupMembers::$integrationFilterStatuses);
                            } #get rows where respond_status contains any error
                        )
                    );
                }

                if ($searchTerm = $this->request->searchText) {
                    $membersQuery->whereRaw("(CONCAT(`f_name`, ' ', `l_name`) LIKE ? OR fb_id = ? OR email = ?)",
                        ["%$searchTerm%", $searchTerm, $searchTerm]
                    );
                }

                if ($sort = $this->request->sort) {
                    $membersQuery->reorder($sort['sortName'], $sort['sortOrder']);
                }

                if ($this->request->page && $this->request->perPage) {
                    $paginator = $membersQuery->paginate($this->request->perPage);
                    $membersFound = $paginator->total();
                } else {
                    $membersFound = $membersQuery->count();
                }
            },
            'members.tags',
            'members.invited_by:id,f_name,l_name',
            'responder',
            'recommendedTags',
            'members.approvedBy',
        ])->where('id', $group_id);
        $query = $query->first();
        return response()->json(
            [
                'code' => Response::HTTP_OK,
                'data' => [
                    'group' => $query,
                    'members_found' => $membersFound,
                ],
            ]
        );
    }

    /**
     * Returns tags which are assigned to the group members
     *
     * @param int $id of the requested Group for getting the tags
     *
     * @return JsonResponse with:
     *                           1. the HTTP code 200 if there are tags or there is no tag in the user groups
     *                           2. data that contain all tags that user use in the groups
     */
    public function getGroupsTag(int $id)
    {
        return response()->json([
            'code' => Response::HTTP_OK,
            'data' => Tag::where('group_id', $id)->has('members')->get(),
        ]);
    }

    /**
     * Updates the group member with the requested data
     *
     * @param GroupMembers $groupMember
     *
     * @return JsonResponse containing:
     *                      1. the HTTP code 200 if a group member is updated
     *                                       401 if the authenticated user is unauthorized to update group member
     *                                       404 if a group member is not found
     *                                       500 if something went wrong at the server-side
     *                      2. message that returns proper text according to the update status
     */
    public function update(GroupMembers $groupMember): JsonResponse
    {
        if (!$this->currentUser->canAccessGroup($this->request->input('group_id'))) {
            return response()->json([
                'code'    => Response::HTTP_UNAUTHORIZED,
                'message' => 'You do not have an access to this group.',
            ]);
        }

        $groupMember = $groupMember->where('group_id', $this->request->input('group_id'))
            ->find($this->request->input('id'));

        if (!$groupMember) {
            return response()->json([
                'code'    => Response::HTTP_NOT_FOUND,
                'message' => 'Record Not Found.',
            ]);
        }

        $groupMember->fill($this->request->except('date_add_time', 'tags', 'add_tags', 'delete_tags'));
        $groupMember->lives_in = $this->request->lives_in ?? '';
        $groupMember->date_add_time = $this->currentUser->timezone ?
            Carbon::createFromFormat('m-d-Y G:i:s', $this->request->date_add_time, $this->currentUser->timezone)
                ->setTimezone(config('app.timezone'))
            :
            Carbon::createFromFormat('m-d-Y G:i:s', $this->request->date_add_time);

        app(TagService::class)->manageTags(
            $this->request->only(
                'recommended_tags_to_add',
                'recommended_tags_to_delete',
                'tags_to_add',
                'tags_to_delete',
            ),
            $groupMember->group_id,
            [$groupMember->id],
        );

        try {
            $groupMember->update();
        } catch (Exception $e) {
            Bugsnag::notifyException(
                $e,
                function ($report) use ($groupMember) {
                    $report->setMetaData([
                        'Group Member' => $groupMember->toArray()
                    ]);
                }
            );

            return response()->json([
                'code'    => Response::HTTP_INTERNAL_SERVER_ERROR,
                'message' => $e->getMessage(),
            ]);
        }

        return response()->json([
            'code'    => Response::HTTP_OK,
            'message' => 'Update Successfully.',
        ]);
    }

    /**
     * Soft deletes {@see \Illuminate\Database\Eloquent\SoftDeletes::runSoftDelete} the group members
     *
     * @return HttpResponse with:
     *                      1. the HTTP code 200 if group members are deleted
     *                                       401 if the authenticated user is unauthorized to delete group members
     *                                       404 if the group member is not found
     *                                       500 if something went wrong on the server-side
     *                      2. message to be displayed to the user explaining the action status
     */
    public function removeGroupMembers(): HttpResponse
    {
        $groupId = $this->request->input('group_id');
        if (!$this->currentUser->canAccessGroup($groupId)) {
            return response(
                [
                    'message' => 'You do not have access to delete these group members',
                ],
                Response::HTTP_UNAUTHORIZED
            );
        }

        $selectedMembersAreOverSyncLimit = count($this->request->selected_member_ids)
            > config('queue.sync_limit.members')
        ;
        $asyncRequest = $this->request->is_multi_page_select_all || $selectedMembersAreOverSyncLimit;

        if ($asyncRequest) {
            $whereClause = $selectedMembersAreOverSyncLimit ? 'whereIn' : 'whereNotIn';
            $memberIdsToBeDeleted = GroupMembers::filterBy($this->request)
                ->where('group_id', $groupId)
                ->$whereClause(
                    'id',
                    $selectedMembersAreOverSyncLimit
                        ? $this->request->selected_member_ids
                        : $this->request->excluded_member_ids
                )
                ->pluck('id')
                ->toArray();
        } else {
            $memberIdsToBeDeleted = $this->request->selected_member_ids;
        }

        $removeMembersJob = new RemoveMembersJob($memberIdsToBeDeleted, $groupId);

        if ($asyncRequest) {
            // dispatch asynchronously, return job id to be checked
            $id = app(Dispatcher::class)->dispatch($removeMembersJob);
            $response = [
                'async' => true,
                'job_id' => $id,
                'message' => __(
                    'Bulk removal of selected members is successfully scheduled. ' .
                    'You will be notified once it is completed. '
                ),
                'async_message' => __(
                    'Members have been removed successfully.'
                ),
            ];
        } else {
            // execute synchronously, return actual status
            $count = $removeMembersJob->handle();
            $response = [
                'async' => false,
                'message' => __('Successfully removed :count members', ['count' => $count]),
            ];
        }

        return response($response);
    }

    /**
     * Send a group member info to integration API
     *
     * @return JsonResponse return success if member added to integration successfully, otherWise error
     */
    public function sendToIntegration(): JsonResponse
    {
        return response()->json([
            'message' => __('Your selected group member were re-sent to your configured integration.'),
        ]);
    }

    /**
     * Manages creating/deleting tags for multiple group members
     *
     * @return HttpResponse with status message and
     *                      HTTP code {@see Response::HTTP_OK} if everything is okay,
     *                      otherwise {@see Response::HTTP_INTERNAL_SERVER_ERROR}
     */
    public function bulkManageTags(): HttpResponse
    {
        $tags = $this->request->only(
            'recommended_tags_to_add',
            'recommended_tags_to_delete',
            'tags_to_add',
            'tags_to_delete',
        );

        if ($this->request->is_multi_page_select_all) {
            $groupMembersIds = GroupMembers::filterBy($this->request)
                ->where('group_id', $this->request->group_id)
                ->whereNotIn('id', $this->request->excluded_member_ids)
                ->pluck('id')
                ->toArray();
        } else {
            $groupMembersIds = $this->request->selected_member_ids;
        }

        $status = Response::HTTP_OK;
        $manageTagsJob = new ManageTagsJob($tags, $this->request->group_id, $groupMembersIds);
        if (count($groupMembersIds) > config('queue.sync_limit.members')) {
            $jobId = app(Dispatcher::class)->dispatch($manageTagsJob);

            $response = [
                'async' => true,
                'message' => __('Tags to ' . count($groupMembersIds) . ' members are successfully added.'),
                'job_id' => $jobId,
            ];
        } else {
            try {
                $manageTagsJob->handle();

                $response = [
                    'async' => false,
                    'message' => __('Successfully tagged :count members.', ['count' => count($groupMembersIds)]),
                ];
            } catch (Exception $e) {
                $response = [
                    'async' => false,
                    'message' => __('Something went wrong'),
                ];
                $status = Response::HTTP_INTERNAL_SERVER_ERROR;
            }
        }

        return response($response, $status);
    }

    /**
     * Filters tags by selected users.
     *
     * @return HttpResponse with labels that belongs to the selected members.
     */
    public function getMembersTagsList(): HttpResponse
    {
        $whereInClause = $this->request->is_multi_page_select_all
            ? 'whereNotIn'
            : 'whereIn';

        $groupMembersTags = GroupMembers::filterBy($this->request)
            ->selectRaw('DISTINCT(tags.label) as label')
            ->$whereInClause(
                'group_members.id',
                $this->request->is_multi_page_select_all
                    ? $this->request->input('excluded_member_ids', [])
                    : $this->request->input('selected_member_ids', [])
            )
            ->where('group_members_tags.group_id', $this->request->group_id)
            ->join('group_members_tags', 'group_members.id', '=', 'group_members_tags.group_member_id')
            ->join('tags', 'group_members_tags.tag_id', '=', 'tags.id')
            ->groupBy('tags.id')
            ->get()
            ->toArray();

        return response(array_column($groupMembersTags, 'label'));
    }

    /**
     * Builds (or schedules building of) group members CSV export file based on the provided filters
     *
     * @return HttpResponse the name of the created csv file
     */
    public function buildCsv(): HttpResponse
    {
        if (!$this->currentUser->canAccessGroup($groupId = $this->request->group_id)) {
            return response(
                ['message' => 'You do not have an access to this group'],
                Response::HTTP_UNAUTHORIZED
            );
        }
        $isMultiPageSelectAll = $this->request->is_multi_page_select_all;

        $whereClause = $isMultiPageSelectAll ? 'whereNotIn' : 'whereIn';

        /** @var \Illuminate\Database\Eloquent\Builder $membersExport */
        $membersExport = GroupMembers::filterBy($this->request)
                ->where('group_id', $groupId)
                ->$whereClause(
                    'id',
                    $isMultiPageSelectAll
                        ? $this->request->input('excluded_member_ids', [])
                        : $this->request->input('selected_member_ids', [])
                );

        $selectedMembersAreOverSyncLimit = $membersExport->count() > config('queue.sync_limit.members');
        $memberIdsForCSV = $membersExport->pluck('id')->toArray();

        $facebookGroup = FacebookGroups::find($groupId);

        $timestamp = now()->timestamp;
        $fileName = $this->currentUser->timezone
            ? "{$facebookGroup->fb_id}_GROUPKIT__" . str_replace('/', '_', $this->currentUser->timezone)
              . "__{$timestamp}.csv"
            : "{$facebookGroup->fb_id}_GROUPKIT_{$timestamp}.csv";

        $buildMembersCSVJob = new BuildMembersCSVFile($memberIdsForCSV, $fileName, $groupId);

        $statusCode = Response::HTTP_OK;
        try {
            if ($selectedMembersAreOverSyncLimit) {
                $lock = (new UniqueLock(Container::getInstance()->make(Cache::class)))->acquire($buildMembersCSVJob);
                if (!$lock) {
                    throw new InvalidStateException(__('Identical action is currently being performed, please wait'));
                }
                // dispatch asynchronously, return job id to be checked
                $response = [
                    'async' => true,
                    'job_id' => app(Dispatcher::class)->dispatch($buildMembersCSVJob),
                    'file_name' => $fileName,
                    'message' => __(
                        'Please wait while we build CSV file for you. ' .
                        'It will be downloaded in a few moments. '
                    ),
                ];
            } else {
                // execute synchronously, return actual status
                $buildMembersCSVJob->handle();

                $response = [
                    'async' => false,
                    'file_name' => $fileName,
                    'message' => __('Successfully created group members CSV file'),
                ];
            }
        } catch (InvalidStateException | BindingResolutionException $exception) {
            $response = ['message' => $exception->getMessage()];
            $statusCode = Response::HTTP_INTERNAL_SERVER_ERROR;
        } catch (FileIsNotCreatedException $exception) {
            $response = ['message' => $exception->getResponseStatus()];
            $statusCode = Response::HTTP_INTERNAL_SERVER_ERROR;
        } finally {
            return response($response, $statusCode);
        }
    }
}
