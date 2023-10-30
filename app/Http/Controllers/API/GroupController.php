<?php

namespace App\Http\Controllers\API;

use App\FacebookGroups;
use App\GroupMembers;
use App\AutoResponder;
use App\Plan;
use App\Services\TagService;
use App\Traits\GroupTrait;
use App\User;
use Bugsnag\BugsnagLaravel\Facades\Bugsnag;
use DateTimeZone;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Str;
use Stripe\Exception\ApiErrorException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * The API end-point for maintaining Facebook group and group member data
 *
 * @package App\Http\Controllers\API
 */
class GroupController extends AbstractApiController
{
    use GroupTrait;

    /**
     * @var array The rules used for validation for each action with the action name used as the key
     */
    protected array $ajaxValidatorRules = [
        'addMembers' => [
            'group'    => 'required|array',
            'members' => 'required|array',
        ],
        'setColumnsVisibility' => [
            'groupId' => 'required|integer|exists:facebook_groups,id',
            'columnsVisibility' => 'required|array',
        ],
        'getMembersNames' => [
            'group_id' => 'required|integer|exists:facebook_groups,id',
            'excluded_member_ids' => 'present|array',
            'selected_member_ids' => 'required_if:is_multi_page_select_all,false|array',
            'is_multi_page_select_all' => 'required|bool',
        ],
        'setColumnsWidth' => [
            'columnsWidth' => 'required|array',
            'groupId' => 'required|integer',
        ],
    ];

    /**
     * Display a listing of the resource.
     *
     * @return HttpResponse
     */
    public function index()
    {
        return $this->filterGroups();
    }

    /**
     * Display a listing of the resource.
     *
     * @return HttpResponse
     */
    public function groupFilterByID()
    {
        return $this->filterGroups();
    }

    /**
     * Aggregates a collection of groups and the meta information concerning daily and weekly
     * members that have been added and sent to integrations
     *
     * @return \Illuminate\Http\JsonResponse containing the current user's groups
     * and metadata for their daily and weekly actions
     */
    public function filterGroups(): \Illuminate\Http\JsonResponse
    {
        $id = $this->currentUser->id;

        $user = User::with([
            'groupsOwned.membersCount',
            'groupsOwned.responder',
            'teamMemberGroupAccess.membersCount',
            'teamMemberGroupAccess.responder',
        ])->find($id);

        $groupIds = $user->teamMemberGroupAccess->pluck('id');

        $defaultTimezone = config('app.timezone');

        $startOfDay = Carbon::now($this->currentUser->timezone)->startOfDay()
            ->setTimezone($defaultTimezone)
            ->format('Y-m-d H:i:s');

        $startOfWeek = Carbon::now($this->currentUser->timezone)->startOfWeek()
            ->setTimezone($defaultTimezone)
            ->format('Y-m-d H:i:s');
        $endOfWeek = Carbon::now($this->currentUser->timezone)->endOfWeek()
            ->setTimezone($defaultTimezone)
            ->format('Y-m-d H:i:s');

        $membersAddedThisWeek = DB::table('group_members')
            ->join('facebook_groups', 'group_members.group_id', '=', 'facebook_groups.id')
            ->whereNull('facebook_groups.deleted_at')
            ->whereNull('group_members.deleted_at')
            ->where('group_members.is_approved', 1)
            ->where('date_add_time', '>=', $startOfWeek)
            ->where('date_add_time', '<=', $endOfWeek);

        $membersAddedToday = DB::table('group_members')
            ->join('facebook_groups', 'group_members.group_id', '=', 'facebook_groups.id')
            ->whereNull('facebook_groups.deleted_at')
            ->whereNull('group_members.deleted_at')
            ->where('group_members.is_approved', 1)
            ->where('group_members.date_add_time', '>=', $startOfDay);

        $emailsAddedThisWeek = DB::table('group_members')
            ->join('facebook_groups', 'group_members.group_id', '=', 'facebook_groups.id')
            ->whereNull('facebook_groups.deleted_at')
            ->whereNull('group_members.deleted_at')
            ->whereNotNull('group_members.email')
            ->where('group_members.respond_status', GroupMembers::RESPONSE_STATUSES['ADDED'])
            ->where('respond_date_time', '>=', $startOfWeek)
            ->where('respond_date_time', '<=', $endOfWeek);

        $emailsAddedToday = DB::table('group_members')
            ->join('facebook_groups', 'group_members.group_id', '=', 'facebook_groups.id')
            ->whereNull('facebook_groups.deleted_at')
            ->whereNull('group_members.deleted_at')
            ->whereNotNull('group_members.email')
            ->where('group_members.respond_status', GroupMembers::RESPONSE_STATUSES['ADDED'])
            ->where('group_members.respond_date_time', '>=', $startOfDay);

        if ($this->currentUser->groupsOwned->isNotEmpty()) {
            $membersAddedThisWeek = $membersAddedThisWeek->where('facebook_groups.user_id', $id);
            $membersAddedToday = $membersAddedToday->where('facebook_groups.user_id', $id);
            $emailsAddedThisWeek = $emailsAddedThisWeek->where('facebook_groups.user_id', $id);
            $emailsAddedToday = $emailsAddedToday->where('facebook_groups.user_id', $id);
        } else {
            $membersAddedThisWeek = $membersAddedThisWeek->whereIn('facebook_groups.id', $groupIds);
            $membersAddedToday = $membersAddedToday->whereIn('facebook_groups.id', $groupIds);
            $emailsAddedThisWeek = $emailsAddedThisWeek->whereIn('facebook_groups.id', $groupIds);
            $emailsAddedToday = $emailsAddedToday->whereIn('facebook_groups.id', $groupIds);
        }

        if ($this->request->id && isset($this->request->id)) {
            $membersAddedThisWeek = $membersAddedThisWeek->where('facebook_groups.id', $this->request->id);
            $membersAddedToday = $membersAddedToday->where('facebook_groups.id', $this->request->id);
            $emailsAddedThisWeek = $emailsAddedThisWeek->where('facebook_groups.id', $this->request->id);
            $emailsAddedToday = $emailsAddedToday->where('facebook_groups.id', $this->request->id);
        }

        return response()->json(
            [
                'code' => Response::HTTP_OK,
                'data' => [
                    'groups' => $user->teamMemberGroupAccess->merge($user->groupsOwned),
                    'weeks_members_count' => $membersAddedThisWeek->select('group_members.*')->count(),
                    'todays_members_count' => $membersAddedToday->select('group_members.*')->count(),
                    'weeks_emails_added_count' => $emailsAddedThisWeek->select('group_members.*')->count(),
                    'todays_emails_added_count' => $emailsAddedToday->select('group_members.*')->count(),
                ],
            ]
        );
    }

    /**
     * Display a listing of the resource.
     *
     * @param int $id of the provided group to get details
     *
     * @return HttpResponse
     */
    public function groupDetails(int $id)
    {
        return response(
            [
                'code' => Response::HTTP_OK,
                'data' => [
                    'group' => FacebookGroups::with(['responder', 'membersCount'])->find($id),
                ],
            ]
        );
    }

    /**
     * Soft deletes {@see \Illuminate\Database\Eloquent\SoftDeletes::runSoftDelete} the group
     *
     * @param int $id of the provided group to be deleted
     *
     * @return JsonResponse containing:
     *                      1. the HTTP code 200 if the group is successfully deleted
     *                                       401 if the authenticated user is unauthorized to delete the group
     *                                       500 if something went wrong at the server-side
     *                      2. message that returns proper text according to the delete group status
     *                      3. data that returns an empty string
     */
    public function destroy(int $id)
    {
        $group = app(FacebookGroups::class)->where('user_id', $this->currentUser->id)->find($id);

        if (!$group) {
            return response()->json([
                'code'    => Response::HTTP_UNAUTHORIZED,
                'message' => 'You do not have access to delete this group.',
                'data'    => '',
            ]);
        }

        DB::beginTransaction();
        try {
            GroupMembers::where('group_id', $group->id)->delete();
            AutoResponder::where('group_id', $group->id)->delete();
            $group->delete();

            DB::commit();
        } catch (\Exception $e) {
            Bugsnag::notifyException(
                $e,
                function ($report) use ($group) {
                    $report->setMetaData([
                        'Group' => $group->toArray()
                    ]);
                }
            );
            DB::rollBack();
            return response()->json([
                'code'    => Response::HTTP_INTERNAL_SERVER_ERROR,
                'message' => $e->getMessage(),
                'data'    => '',
            ]);
        }

        return response()->json([
            'code'    => Response::HTTP_OK,
            'message' => 'Your group has been removed.',
            'data'    => '',
        ]);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param array $data to be store in auto_responder table
     * @param string $type of the responder
     * @param int $group_id represents id of the {@see FacebookGroups}
     * @param int $user_id represents the id of the group owner
     */
    public function Autoresponder($data, $type, $group_id, $user_id)
    {
        $responder = AutoResponder::where('group_id', $group_id)->where('user_id', $user_id)->first();

        if (!$responder) {
            $responder = new AutoResponder();
        }

        $responder->responder_type = $type;
        $responder->responder_json = json_encode($data);
        $responder->user_id = $user_id;
        $responder->group_id = $group_id;
        $responder->is_check = 1;
        $responder->save();
    }

    /**
     * Store a newly created resource in storage.
     *
     * @return JsonResponse sent back to the client describing the success or failure of the legacy import
     */
    public function importTextFile()
    {
        set_time_limit(360); # 6 minutes max execution time
        try {
            if (
                is_null($this->request->fbids)
                || is_null($this->request->fbnames)
                || count($this->request->fbids) !== count($this->request->fbnames)
            ) {
                return response()->json(
                    [
                        'code' => Response::HTTP_BAD_REQUEST,
                        'message' => 'The imported file seems to be incomplete. Please try again by uploading a new file.',
                        'data' => '',
                    ]
                );
            }

            $groupIds = $this->request->fbids;
            $groupNames = $this->request->fbnames;
            $members = $this->request->data;
            $userid = $this->currentUser->id;

            foreach ($groupNames as $key => $val) {
                $groupId = $groupIds[$key];

                if (
                    !$this->currentUser->getOwnedGroupByFacebookId($groupId)
                    && !$this->currentUser->canAddAnother('group')
                ) {
                    # If the customer tries to import a group he doesn't already own and reached plan group limit
                    return response()->json([
                        'code'    => Response::HTTP_FORBIDDEN,
                        'message' => __('Your plan\'s group limit has been reached. ') . $this->upgradePlanLink(),
                    ]);
                }

                /* Add Groups */
                $previousFacebookGroup = FacebookGroups::withTrashed()
                    ->where('fb_id', $groupId)
                    ->where('user_id', $userid)
                    ->first();

                $facebookGroup = $previousFacebookGroup ?? new FacebookGroups();
                $facebookGroup->deleted_at = null;
                $facebookGroup->user_id = $userid;
                $facebookGroup->fb_name = $val;
                $facebookGroup->fb_id = $groupId;
                $facebookGroup->save();

                $facebookGroupId = $facebookGroup->id;
                /* Add Autoresponder */

                /* Aweber */
                if ($this->request->aweber != null && $this->request->aweber) {
                    $responder = array_filter(
                        $this->request->aweber,
                        function ($val) use ($groupId) {
                            if (trim($val['fb_id']) == trim($groupId)) {
                                return $val;
                            }
                        }
                    );

                    if (count($responder)) {
                        $responder = array_values(array_filter($responder));
                        $responder = (object)$responder[0];
                        $responder_json = [
                            'activeList' => ['label' => $responder->list_name, 'value' => $responder->list_id],
                            'access_token' => $responder->access_token,
                            'refresh_token' => $responder->refresh_token,
                            'account_id' => $responder->acc_id,
                            'client_id' => 'uN922fsr2kQDjN2R2SIcW2NWsb3WbaCG',
                        ];
                        $this->Autoresponder($responder_json, 'Aweber', $facebookGroupId, $userid);
                    }
                }

                /* ActiveCampaign */
                if ($this->request->active != null && $this->request->active) {
                    $responder = array_filter(
                        $this->request->active,
                        function ($val) use ($groupId) {
                            if (trim($val['fb_id']) == trim($groupId)) {
                                return $val;
                            }
                        }
                    );

                    if (count($responder)) {
                        $responder = array_values(array_filter($responder));
                        $responder = (object)$responder[0];
                        $responder_json = [
                            'activeList' => ['label' => '', 'value' => $responder->listid],
                            'activeTags' => ['label' => '', 'value' => $responder->tagid],
                            'host_name' => $responder->username,
                            'api_key' => $responder->apik,
                        ];
                        $this->Autoresponder($responder_json, 'ActiveCampaign', $facebookGroupId, $userid);
                    }
                }

                /* Getresponse */
                if ($this->request->getr != null && $this->request->getr) {
                    $responder = array_filter(
                        $this->request->getr,
                        function ($val) use ($groupId) {
                            if (trim($val['fb_id']) == trim($groupId)) {
                                return $val;
                            }
                        }
                    );

                    if (count($responder)) {
                        $responder = array_values(array_filter($responder));
                        $responder = (object)$responder[0];
                        $responder_json = [
                            'activeList' => ['label' => $responder->list_name, 'value' => $responder->list_id],
                            'api_key' => $responder->apikey,
                        ];
                        $this->Autoresponder($responder_json, 'Getresponse', $facebookGroupId, $userid);
                    }
                }

                /* ConvertKit */
                if ($this->request->conv != null && $this->request->conv) {
                    $responder = array_filter(
                        $this->request->conv,
                        function ($val) use ($groupId) {
                            if (trim($val['fb_id']) == trim($groupId)) {
                                return $val;
                            }
                        }
                    );

                    if (count($responder)) {
                        $responder = array_values(array_filter($responder));
                        $responder = (object)$responder[0];
                        $responder_json = [
                            'activeList' => ['label' => $responder->list_name, 'value' => $responder->list_id],
                            'api_key' => $responder->apikey,
                        ];
                        $this->Autoresponder($responder_json, 'ConvertKit', $facebookGroupId, $userid);
                    }
                }

                /* MailChimp */
                if ($this->request->mailchimp != null && $this->request->mailchimp) {
                    $responder = array_filter(
                        $this->request->mailchimp,
                        function ($val) use ($groupId) {
                            if (trim($val['fb_id']) == trim($groupId)) {
                                return $val;
                            }
                        }
                    );

                    if (count($responder)) {
                        $responder = array_values(array_filter($responder));
                        $responder = (object)$responder[0];
                        $responder_json = [
                            'activeList' => ['label' => '', 'value' => $responder->list_id],
                            'api_key' => $responder->apik,
                        ];
                        $this->Autoresponder($responder_json, 'MailChimp', $facebookGroupId, $userid);
                    }
                }

                /* GoHighLevel */
                if ($this->request->gohigh != null && $this->request->gohigh) {
                    $responder = array_filter(
                        $this->request->gohigh,
                        function ($val) use ($groupId) {
                            if (trim($val['fb_id']) == trim($groupId)) {
                                return $val;
                            }
                        }
                    );

                    if (count($responder)) {
                        $responder = array_values(array_filter($responder));
                        $responder = (object)$responder[0];
                        $responder_json = [
                            'activeList' => ['label' => '', 'value' => $responder->list_id],
                            'api_key' => $responder->apik
                        ];
                        $this->Autoresponder($responder_json, 'GoHighLevel', $facebookGroupId, $userid);
                    }
                }

                /* Kartra */
                if ($this->request->kantra != null && $this->request->kantra) {
                    $responder = array_filter(
                        $this->request->kantra,
                        function ($val) use ($groupId) {
                            if (trim($val['fb_id']) == trim($groupId)) {
                                return $val;
                            }
                        }
                    );

                    if (count($responder)) {
                        $responder = array_values(array_filter($responder));
                        $responder = (object)$responder[0];
                        $responder_json = [
                            'activeList' => ['label' => $responder->listid, 'value' => $responder->listid],
                            'api_key' => $responder->username,
                            'password' => $responder->apik,
                            'app_id' => 'YpEJORItQUab',
                        ];
                        $this->Autoresponder($responder_json, 'Kartra', $facebookGroupId, $userid);
                    }
                }

                /* Mailerlite */
                if ($this->request->mailerlite != null && $this->request->mailerlite) {
                    $responder = array_filter(
                        $this->request->mailerlite,
                        function ($val) use ($groupId) {
                            if (trim($val['fb_id']) == trim($groupId)) {
                                return $val;
                            }
                        }
                    );

                    if (count($responder)) {
                        $responder = array_values(array_filter($responder));
                        $responder = (object)$responder[0];
                        $responder_json = [
                            'activeList' => ['label' => '', 'value' => $responder->listid],
                            'api_key' => $responder->apikey,
                        ];
                        $this->Autoresponder($responder_json, 'Mailerlite', $facebookGroupId, $userid);
                    }
                }

                /* GoogleSheet */
                if ($this->request->sheets != null && $this->request->sheets) {
                    $responder = array_filter(
                        $this->request->sheets, function ($val) use ($groupId) {
                        if (trim($val['fb_id']) == trim($groupId)) {
                            return $val;
                        }
                    }
                    );

                    if (count($responder)) {
                        $responder = array_values(array_filter($responder));
                        $responder = (object)$responder[0];
                        $responder_json = [
                            'activeList' => ['label' => '', 'value' => 1],
                            'sheetURL' => 'https://docs.google.com/spreadsheets/d/' . $responder->sheet,
                        ];
                        $this->Autoresponder($responder_json, 'GoogleSheet', $facebookGroupId, $userid);
                    }
                }

                $tagsToImport = []; #for bulk import tags

                /* adding members in bulk to DB */
                $formattedGroupMemberData = [];
                foreach ($this->request->data as $groupMember) {
                    if (trim($groupMember['fb_id']) !== trim($facebookGroup->fb_id)) {
                        continue;
                    }
                    // get the unique, non-empty values for keywords.
                    $rowid = $groupMember['rowid'];
                    $keywords = array_map(
                        function ($tags) use ($rowid) {
                            if (trim($rowid) == trim($tags['user_id'])) {
                                return $tags['keyword'];
                            }
                        },
                        $this->request->keywords
                    );
                    $keywords = array_values(array_unique(array_filter($keywords)));
                    $tagsToImport[] = $keywords;

                    /*
                     * Check if the value for all the fields are there,
                     * if not then we should set it to null
                     */
                    $groupMember['fb_id'] = $groupMember['user_id'];
                    $groupMember['f_name'] = @$groupMember['f_name'] ?? null;
                    $groupMember['l_name'] = @$groupMember['l_name'] ?? null;
                    $groupMember['a1'] = @$groupMember['a1'] ?? null;
                    $groupMember['a2'] = @$groupMember['a2'] ?? null;
                    $groupMember['a3'] = @$groupMember['a3'] ?? null;
                    $groupMember['notes'] = @$groupMember['notes'] ?? null;
                    $groupMember['email'] = (@$groupMember['email'] === '-')
                        ? null
                        : $groupMember['email'];
                    $groupMember['respond_status'] = ($groupMember['respond_status'] == 'N/A') ?
                        GroupMembers::RESPONSE_STATUSES['NOT_ADDED'] :
                        $groupMember['respond_status'] ?? GroupMembers::RESPONSE_STATUSES['NOT_ADDED'];
                    $groupMember['date_add_time'] = @$groupMember['date_add_time'] ?
                        date('Y-m-d H:i:s', $groupMember['date_add_time']) :
                        now();
                    $groupMember['user_id'] = $this->currentUser->id;
                    $groupMember['group_id'] = $facebookGroup->id;
                    $groupMember['deleted_at'] = null;
                    $groupMember['created_at'] = now();
                    $groupMember['updated_at'] = now();
                    // unset column rowid, id
                    unset($groupMember['rowid']);
                    unset($groupMember['id']);

                    $formattedGroupMemberData[] = $groupMember;
                }

                if ($formattedGroupMemberData[0]) {
                    $groupMembersChunks = array_chunk(
                        $formattedGroupMemberData,
                        config('database.connections.mysql.chunk_size')
                    );

                    $primaryKeys = ['user_id', 'fb_id', 'group_id'];
                    $columnNamesToUpdateOnDuplicate = array_diff(
                        array_keys($formattedGroupMemberData[0]),
                        array_merge($primaryKeys, ['created_at'])
                    );

                    foreach ($groupMembersChunks as $groupMembersChunk) {
                        app(GroupMembers::class)->upsert(
                            $groupMembersChunk,
                            $primaryKeys,
                            $columnNamesToUpdateOnDuplicate
                        );
                    }

                    $hasTagsToImport = array_filter($tagsToImport);
                    if ($hasTagsToImport) {
                        $this->importGroupMembersTags($tagsToImport, $formattedGroupMemberData, $facebookGroup);
                    }
                }
            }

            return response()->json(
                [
                    'code' => Response::HTTP_OK,
                    'message' => 'Your group members have been imported.',
                    'data' => '',
                ]
            );
        } catch (\Exception $e) {
            Bugsnag::notifyException($e);
            return response()->json(
                [
                    'code' => Response::HTTP_BAD_REQUEST,
                    'message' => 'Invalid Request',
                    'data' => '',
                ]
            );
        }
    }

    /**
     * Adds Group member data via an CSV file import
     *
     * Data comes in the request in the following format:
     *
     * {
     *   "facebook_groups": {
     *     "fb_id": "<Facebook Group ID>",
     *     "fb_name": "<Facebook Group Name>",
     *     "img": "<URL of the Facebook Group Image - currently unused>"
     *   },
     *   "group_members": "<List of users details in name value pairs matching the GroupMember model schema>"
     *   "file_name": "<name of the CSV file>"
     * }
     *
     * @return JsonResponse sent back to the client describing the success or failure of the import
     *
     * @throws ApiErrorException when trying to retrieve user group number limit from Stripe
     *                           {@see \Stripe\ApiRequestor::request}
     */
    public function importCsv()
    {
        $facebookGroupData = $this->request->facebook_groups;
        $facebookGroupFBID = $facebookGroupData['fb_id'];
        if (
            !$this->currentUser->getOwnedGroupByFacebookId($facebookGroupFBID)
            && !$this->currentUser->canAddAnother('group')
        ) {
            # If the customer tries to import a group he doesn't already own and reached plan group limit
            return response()->json([
                'code'    => Response::HTTP_FORBIDDEN,
                'message' =>  __('Your plan\'s group limit has been reached. ') . $this->upgradePlanLink(),
            ]);
        }

        $fileTimezone = null;

        # Sets timezone if it is found in the file name
        if (($fileTimezonePart = Str::between($this->request->file_name, "__", "__"))) {
            # if the CSV contains timezone, its parts will be delimited by '_' so we replace it with '/'
            # e.g. 'Europe_Belgrade' -> 'Europe/Belgrade'
            $fileTimezoneKeyword = Str::replaceFirst('_', '/', $fileTimezonePart);

            if (in_array($fileTimezoneKeyword, DateTimeZone::listIdentifiers())) {
                $fileTimezone = $fileTimezoneKeyword;
            }
        }

        try {
            $previousFacebookGroup = FacebookGroups::withTrashed()
                ->where('fb_id', $facebookGroupFBID)
                ->where('user_id', $this->currentUser->id)
                ->first();

            $facebookGroup = $previousFacebookGroup ?? new FacebookGroups();
            $facebookGroup->deleted_at = null;
            $facebookGroup->user_id = $this->currentUser->id;
            $facebookGroup->fill($facebookGroupData)->save();

            $formattedGroupMemberData = [];
            $tagsToImport = [];
            foreach ($this->request->group_members as $groupMember) {
                unset($groupMember['rowid']);
                $groupMember['date_add_time'] = $fileTimezone ?
                    Carbon::createFromFormat('m-d-Y G:i', $groupMember['date_add_time'], $fileTimezone)
                        ->setTimezone(config('app.timezone'))
                    :
                    Carbon::createFromFormat('m-d-Y G:i', $groupMember['date_add_time']);
                $tagsToImport[] = $groupMember['tags'] ? explode(',', $groupMember['tags']) : [];
                $groupMember['fb_id'] = $groupMember['user_id'];
                $groupMember['user_id'] = $this->currentUser->id;
                $groupMember['group_id'] = $facebookGroup->id;
                $groupMember['created_at'] = now();
                $groupMember['updated_at'] = now();
                $groupMember['deleted_at'] = null;

                unset($groupMember['tags']);

                $formattedGroupMemberData[] = $groupMember;
            }

            $groupMembersChunks = array_chunk(
                $formattedGroupMemberData,
                config('database.connections.mysql.chunk_size')
            );

            $primaryKeys = ['user_id', 'fb_id', 'group_id'];
            $columnNamesToUpdateOnDuplicate = array_diff(
                array_keys($formattedGroupMemberData[0]),
                array_merge($primaryKeys, ['created_at'])
            );

            $importCount = 0;
            foreach ($groupMembersChunks as $groupMembersChunk) {
                $importCount += app(GroupMembers::class)->upsert(
                    $groupMembersChunk,
                    $primaryKeys,
                    $columnNamesToUpdateOnDuplicate
                );
            }

            $hasTagsToImport = array_filter($tagsToImport);
            if ($hasTagsToImport) {
                $this->importGroupMembersTags($tagsToImport, $formattedGroupMemberData, $facebookGroup);
            }

            return response()->json(
                [
                    'code' => Response::HTTP_OK,
                    'message' =>
                        "Successfully imported data!\nUpdates may take a moment to be reflected in your dashboard.",
                    'data' => [],
                ]
            );
        } catch (\Exception $e) {
            Bugsnag::notifyException($e);
            return response()->json(
                [
                    'code' => Response::HTTP_INTERNAL_SERVER_ERROR,
                    'message' => 'Oops!  There was a problem processing your import.  Please try again later.',
                    'data' => ['error_code' => $e->getCode()],
                ]
            );
        }
    }

    /**
     * Adds facebook group members from the request to the database
     *
     * @return HttpResponse with a success message if group members are added, otherwise fail message
     */
    public function addMembers(): HttpResponse
    {
        $facebookGroup = FacebookGroups::withTrashed()
            ->where('user_id', $this->currentUser->id)
            ->where('fb_id', $this->request->group['groupid'])
            ->first();

        try {
            if (
                (!$facebookGroup || $facebookGroup->deleted_at)
                && !$this->currentUser->canAddAnother('group')
            ) {
                return response(
                    [
                        'message' => __('Your plan\'s group limit has been reached. ') . $this->upgradePlanLink(),
                    ],
                    Response::HTTP_UNPROCESSABLE_ENTITY
                );
            }

            $facebookGroupData = $this->request->group;

            # Update the Facebook group details
            if (!$facebookGroup) {
                $facebookGroup = new FacebookGroups();
            }

            $facebookGroup->fb_name = $facebookGroupData['groupname'];
            $facebookGroup->fb_id = $facebookGroupData['groupid'];
            @$facebookGroupData['img'] ? $facebookGroup->img = $facebookGroupData['img'] : "";
            $facebookGroup->user_id = $this->currentUser->id;
            $facebookGroup->deleted_at = null;
            $facebookGroup->save();

            $insertGroupMembers = []; #array for bulk insert into database

            # Prepare facebook group members for import
            foreach ($this->request->members as $facebookGroupMember) {
                #################################################################################
                # Add data to array for bulk insert
                #################################################################################
                $insertGroupMembers[] = [
                    'date_add_time'  => date('Y-m-d H:i:s'),
                    'user_id'        => $this->currentUser->id,
                    'group_id'       => $facebookGroup->id,
                    'fb_id'          => $facebookGroupMember['user_id'],
                    'f_name'         => $facebookGroupMember['f_name'],
                    'l_name'         => @$facebookGroupMember['l_name'],
                    'img'            => @$facebookGroupMember['img'],
                    'respond_status' => ($facebookGroupMember['respond_status'] === 'N/A')
                        ? GroupMembers::RESPONSE_STATUSES['NOT_ADDED']
                        : $facebookGroupMember['respond_status'],
                ];
            }

            #insert bulk group members if is not empty
            if ($insertGroupMembers) {
                $groupMemberChunks = array_chunk(
                    $insertGroupMembers,
                    config('database.connections.mysql.chunk_size')
                );

                $primaryKeys = ['user_id', 'fb_id', 'group_id'];
                foreach ($groupMemberChunks as $groupMembers) {
                    app(GroupMembers::class)->upsert($groupMembers, $primaryKeys, ['deleted_at', 'img']);
                }
            }
        } catch (Exception $e) {
            Bugsnag::notifyException($e);
            logger()->error($e->getMessage());
            return response(
                ['message' => __('Something went wrong')],
                Response::HTTP_BAD_REQUEST
            );
        }

        return response(['message' => __('Successfully inserted')]);
    }

    /**
     * Sends group members and tags to the tags bulk import
     *
     * @param array $tagsToImport parallel array of $formattedGroupMemberData with tag for each member
     * @param array $formattedGroupMemberData containing group member that will be connected to the tags
     * @param FacebookGroups $facebookGroup of the provided group members as $formattedGroupMemberData
     *
     * @throws Exception if the count of the tags is not equal to the count of the group members
     */
    private function importGroupMembersTags(
        array $tagsToImport,
        array $formattedGroupMemberData,
        FacebookGroups $facebookGroup
    ) {
        if (count($tagsToImport) !== count($formattedGroupMemberData)) {
            throw new Exception(
                __('Number of the tags are not equal to the number of the group members'),
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        app()->terminating(
            function () use ($tagsToImport, $formattedGroupMemberData, $facebookGroup) {
                app(TagService::class)->bulkStoreOrUpdate(
                    $tagsToImport,
                    $formattedGroupMemberData,
                    $facebookGroup->id,
                    $this->currentUser->id
                );
            }
        );
    }

    /**
     * Persists which columns are displayed on a user's group details page.
     *
     * @return JsonResponse with result of the inserting adjusted columns into database
     */
    public function setColumnsVisibility(): JsonResponse
    {
        $this->currentUser->groupColumnsSettings()
            ->sync(
                [
                    $this->request->groupId => ['columns_visibility' => json_encode($this->request->columnsVisibility)],
                ],
                false
            );

        return response()->json(['message' => __('Columns visibility state stored successfully.')]);
    }

    /**
     * Returns columns settings by group ID and authenticated user.
     *
     * @param FacebookGroups $facebookGroup which adjusted column will be returned
     *
     * @return JsonResponse including columns visibility array
     */
    public function getColumnsVisibility(FacebookGroups $facebookGroup): JsonResponse
    {
        return response()->json(['data' => $this->currentUser->getColumnsSettingsByGroup($facebookGroup->id)]);
    }

    /**
     * Generates a collection of GroupMembers models by group ID, and members IDs,
     * or excludes members which ids are not in the scope of search results if the
     * option is_multi_page_select_all is false.
     *
     * @return JsonResponse as collection of members' 'id', 'fb_id', 'f_name', 'l_name',
     * and FB group ID.
     */
    public function getMembersNames(): JsonResponse
    {
        $whereInClause = $this->request->is_multi_page_select_all
            ? 'whereNotIn'
            : 'whereIn';

        $members = GroupMembers::filterBy($this->request)
            ->selectRaw('CONCAT(`f_name`, " ", `l_name`) AS `full_name`, `fb_id`, `id`')
            ->where('group_id', $this->request->group_id)
            ->$whereInClause(
                'id',
                $this->request->is_multi_page_select_all
                    ? $this->request->input('excluded_member_ids', [])
                    : $this->request->input('selected_member_ids', [])
            )
            ->get();

        $fbGroupId = FacebookGroups::where('id', $this->request->group_id)->first()->fb_id;

        return response()->json([
            'members' => $members,
            'fbGroupId' => (int) $fbGroupId,
        ]);
    }

    /**
     * Stores columns width in user's group settings.
     *
     * @return JsonResponse with result of the inserting columns width into database
     */
    public function setColumnsWidth(): JsonResponse
    {
        if (!$this->currentUser->canAccessGroup($this->request->groupId)) {
            return response()->json(
                ['message' => __('You do not have access to set columns width for provided group')],
                Response::HTTP_UNAUTHORIZED
            );
        }

        $this->currentUser->groupColumnsSettings()
            ->sync(
                [
                    $this->request->groupId => ['columns_width' => json_encode($this->request->columnsWidth)],
                ],
                false
            );

        return response()->json(['message' => __('Columns width stored successfully.')]);
    }

    /**
     * Returns group settings by group ID and authenticated user.
     *
     * @param FacebookGroups $facebookGroup which adjusted column will be returned
     *
     * @return JsonResponse including group settings (columns visibility, columns width)
     */
    public function getGroupSettings(FacebookGroups $facebookGroup): JsonResponse
    {
        return response()->json([
            'group_settings' => $this->currentUser->getGroupSettings($facebookGroup->id),
        ]);
    }
}
