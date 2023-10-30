<?php

namespace App\Http\Controllers\API;

use App\AutoResponder;
use App\FacebookGroups;
use App\GroupMembers;
use App\Jobs\AddMembers;
use App\Jobs\MarketingAutomation\GoogleSheet\FormatGoogleSheetDates;
use App\OwnerToTeamMember;
use App\Plan;
use App\Services\MarketingAutomation\GoogleSheetService;
use App\Traits\GroupTrait;
use App\User;
use Bugsnag\BugsnagLaravel\Facades\Bugsnag;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Stripe\Exception\ApiErrorException;
use Symfony\Component\HttpFoundation\Response;

class ScrapingController extends AbstractApiController
{
    use GroupTrait;

    /**
     * Message returned on bad request
     *
     * @var string
     */
    public const BAD_REQUEST_MESSAGE = 'Invalid Request';

    /**
     * @var array The rules used for validation for each action with the action name used as the key
     */
    protected array $ajaxValidatorRules = [
        'saveAutoresponder' => [
            'group_id' => 'required',
            'responder_type' => 'required'
        ],
        'deleteAutoresponder' => [
            'group_id' => 'required',
        ],
    ];

    /**
     * @var Collection Any pre-existing groups where members are to be added. This is cached for storing
     *
     * @see ScrapingController::validateAddingMembers()
     * @see ScrapingController::store()
     */
    private Collection $matchingFacebookGroups;

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        try {
            $currentUser = $this->currentUser;
            $subscription = app(User::class)->getSubscriptionDetails($currentUser->stripe_id);

            $currentUser->plan_name = $currentUser->subscriptionsPlan($currentUser->id);
            $currentUser->can_have_team = $subscription ?
                (bool)Plan::getPlan($subscription->stripe_plan, ['product'])->product->metadata->moderator_limit
                : false;
            $currentUser->access_team = $currentUser->activePlan();

            unset($currentUser->email_verified_at);
            unset($currentUser->stripe_id);
            unset($currentUser->card_brand);
            unset($currentUser->card_last_four);
            unset($currentUser->trial_ends_at);

            return response()->json(
                [
                    'code' => Response::HTTP_OK,
                    'data' => [
                        'user' => $currentUser
                    ],
                ]
            );
        } catch (\Exception $e) {
            Bugsnag::notifyException($e);
            return response()->json(
                [
                    'code' => Response::HTTP_BAD_REQUEST,
                    'message' => $e->getMessage(),
                    'data' => '',
                ]
            );
        }
    }

    /**
     * Store a newly created resource in storage.
     *
     * @return \Illuminate\Http\Response
     */
    public function store()
    {
        try {
            $validator = Validator::make(
                $this->request->all(),
                [
                    'json' => [
                        'required',
                        function ($attributeName, $value, $fail) {
                            $this->validateAddingMembers($value, $fail);
                        }
                    ]
                ]
            );

            if ($validator->fails()) {
                $error = $validator->errors()->first('json');
                $wasBadRequest = $error === self::BAD_REQUEST_MESSAGE;

                return response()->json(
                    [
                        'code' => $wasBadRequest ? Response::HTTP_BAD_REQUEST : Response::HTTP_OK,
                        'message' => $error,
                        'data' => [],
                        'limit' => !$wasBadRequest, # if it wasn't a bad request, then we failed the limit validation
                    ]
                );
            }

            $groups = $this->request->get('json');
            $requestIsFromExtension = Str::contains($this->request->headers->get('origin'), 'chrome-extension://');

            // Process saving as an asynchronous background job
            AddMembers::dispatch($this->currentUser, $groups, $requestIsFromExtension, $this->matchingFacebookGroups);

            return response()->json(
                [
                    'code' => Response::HTTP_OK,
                    'message' => 'Successfully uploaded.',
                    'data' => [],
                    'limit' => false,
                ]
            );
        } catch (\Exception $e) {
            Bugsnag::setMetaData(['request' => $this->request->all()])->notifyException($e);

            return response()->json(
                [
                    'code' => Response::HTTP_BAD_REQUEST,
                    'message' => $e->getMessage(),
                    'data' => [],
                ]
            );
        }
    }

    /**
     * Validates whether the request to add members is structurally valid and
     * whether the limits will be exceeded if the group(s) or member(s) in the request are added.
     *
     * Note: This validation strategy is all or nothing.  In other words, if any addition to any group is
     * bound to fail for any owner, no additions will be made, even if other groups by other owners would succeed.
     *
     * @param array $groups The group and group member data that is to be added
     * @param callable $fail The validation callback that is invoked on failure
     *
     * @throws ApiErrorException when the Stripe API is unable to be reached in order to check limits
     */
    private function validateAddingMembers(array $groups, callable $fail)
    {
        $currentUser = $this->currentUser;
        $hasReachedMemberLimit = [];
        $hasInactiveAccount = false;

        # when no group members are sent, it's a bad request
        if (!count($groups)) {
            $fail(self::BAD_REQUEST_MESSAGE);
            return;
        }

        foreach ($groups as $groupData) {
            $facebookGroupData = $groupData['group'];
            $facebookGroupMembersToAdd = $groupData['user_details'];
            $facebookGroupsToAddMembers = [];

            $ownedFacebookGroup = ($currentUser->hasSubscription($currentUser->stripe_id))
                ? $currentUser->groupsOwned()
                    ->withTrashed()
                    ->where('fb_id', $facebookGroupData['groupid'])
                    ->get()
                : new Collection(); # not an owner, so we set an empty collection for the subsequent merge

            $managedFacebookGroups = FacebookGroups::where('fb_id', $facebookGroupData['groupid'])
                ->whereIn(
                    'user_id',
                    OwnerToTeamMember::whereIn(
                        'id',
                        $currentUser->teamMemberGroupAccess()->pluck('owner_to_team_member_id')
                    )->pluck('owner_id')
                )
                ->get();

            # Checks if the current User is not the groupOwner and group is not assigned to the TeamMember
            foreach ($managedFacebookGroups as $managedFacebookGroup) {
                if (
                    $managedFacebookGroup->user_id != $currentUser->id
                    && !$currentUser->teamMemberGroupAccess()
                        ->where('facebook_group_id', $managedFacebookGroup->id)
                        ->count()
                ) {
                    // return "Error Message" if it's not group owner and team member doesn't have group access.
                    $fail(__('You are not authorized to perform this action!'));
                    return;
                }
            }

            $this->matchingFacebookGroups = $managedFacebookGroups->merge($ownedFacebookGroup);

            # Update the Facebook group details
            if ($this->matchingFacebookGroups->isNotEmpty()) {
                foreach ($this->matchingFacebookGroups as $facebookGroup) {
                    ############################################################
                    # If this is an owned group that has previously been deleted,
                    # we must check against the user's limit to see if it can be
                    # re-added.
                    ############################################################
                    if (
                        $facebookGroup->user_id === $currentUser->id
                        && $facebookGroup->deleted_at
                        && ($currentUser->getAvailableCountFor('groups') < count($groups))
                    ) {
                        $fail(__('Your plan\'s group limit has been reached.') . ' ' . $this->upgradePlanLink());
                        return;
                    }
                    ############################################################

                    $facebookGroupsToAddMembers[] = $facebookGroup;
                }
            } else {
                # We create a new group since it does not already exist
                # It is important that we check the group creation limits against
                # the current user, who must be a group owner to add a new group
                if ($currentUser->getAvailableCountFor('groups') < count($groups)) {
                    $fail(__('Your plan\'s group limit has been reached.') . ' ' . $this->upgradePlanLink());
                    return;
                }

                $facebookGroup = new FacebookGroups();
                $facebookGroup->user_id = $currentUser->id;
                $facebookGroupsToAddMembers[] = $facebookGroup;
            }

            foreach ($facebookGroupsToAddMembers as $facebookGroup) {
                $groupOwner = app(User::class)->find($facebookGroup->user_id);

                #check if subscription has been paused
                $subscriptionIsPaused = app(User::class)->subscriptionIsPausedForSuspendedService(
                    $groupOwner->id
                );

                $hasInactiveAccount = $subscriptionIsPaused || !$groupOwner->activePlan();

                ########################################
                # If the user trying to go beyond their plan limit,
                # prevent the addition and append an error message
                ########################################
                if (array_key_exists($groupOwner->id, $hasReachedMemberLimit)) {
                    continue;
                }

                if ($groupOwner->getAvailableCountFor('members') < count($facebookGroupMembersToAdd)) {
                    $hasReachedMemberLimit[$groupOwner->id] = "[{$groupOwner->name}]";
                }
            }

            foreach ($facebookGroupMembersToAdd as $facebookGroupMemberToAdd) {
                if (
                    array_key_exists('user_id', $facebookGroupMemberToAdd)
                    && array_key_exists('invited_by_id', $facebookGroupMemberToAdd)
                    && $facebookGroupMemberToAdd['user_id'] === $facebookGroupMemberToAdd['invited_by_id']
                ) {
                    $fail(__('The group member cannot invite himself to the group.'));
                    return;
                }
            }
        }

        if ($hasInactiveAccount) {
            $fail('Subscription has been paused');
            return;
        }

        if ($hasReachedMemberLimit) {
            $fail(
                implode(',', $hasReachedMemberLimit)
                . __(' member limit has been reached. ') .
                $this->upgradePlanLink()
            );
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @return \Illuminate\Http\Response
     */
    public function saveAutoresponder()
    {
        $userId = $this->currentUser->id;
        $group = FacebookGroups::where('id', $this->request->group_id)
            ->where('user_id', $userId)
            ->first();

        $user = User::where('id', $userId)->first();

        if ($group && $user) {
            $responder = AutoResponder::where('group_id', $this->request->group_id)->where('user_id', $userId)->first();

            if (!$responder) {
                $responder = new AutoResponder();
            }

            if (
                $responder->responder_type === 'GoogleSheet'
                && json_decode($responder->responder_json)->dateAddTimeFormat
                    !== $this->request->responder_json['dateAddTimeFormat']
                && array_key_exists(
                    $this->request->responder_json['dateAddTimeFormat'],
                    GoogleSheetService::DATE_FORMATS
                )
            ) {
                # todo: move this logic to GoogleSheetService
                dispatch(
                    new FormatGoogleSheetDates(
                        $group->id,
                        GoogleSheetService::DATE_FORMATS[$this->request->responder_json['dateAddTimeFormat']]
                    )
                );
            }

            $responder->responder_type = $this->request->responder_type;
            $responder->responder_json = json_encode($this->request->responder_json);
            $responder->user_id = $userId;
            $responder->group_id = $this->request->group_id;
            $responder->is_check = $this->request->is_check;
            $responder->save();

            return response()->json(
                [
                    'code' => Response::HTTP_OK,
                    'message' => $responder->responder_type . ' integration saved.',
                    'data' => '',
                ]
            );
        } else {
            /** HTTP_BAD_REQUEST #400 #Bad Request */
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
     * Remove the specified resource from storage.
     *
     * @return \Illuminate\Http\Response
     */
    public function deleteAutoresponder()
    {
        $userId = $this->currentUser->id;
        $group = FacebookGroups::where('id', $this->request->group_id)
            ->where('user_id', $userId)
            ->first();

        $user = User::where('id', $userId)->first();

        if ($group && $user) {
            $responder = AutoResponder::where('group_id', $this->request->group_id)
                ->where('user_id', $userId)
                ->first();

            GroupMembers::where('group_id', $this->request->group_id)
                ->update(['respond_status' => '']);

            if (!$responder) {
                return response()->json(
                    [
                        'code' => Response::HTTP_OK,
                        'message' => 'Record Not Found.',
                        'data' => '',
                    ]
                );
            }

            /**
             * Soft delete
             * @see \Illuminate\Database\Eloquent\SoftDeletes::runSoftDelete
             */
            $responder->delete();

            return response()->json(
                [
                    'code' => Response::HTTP_OK,
                    'message' => 'AutoResponder Removed Successfully.',
                    'data' => '',
                ]
            );
        } else {
            /** HTTP_BAD_REQUEST #400 #Bad Request */
            return response()->json(
                [
                    'code' => Response::HTTP_BAD_REQUEST,
                    'message' => 'Invalid Request',
                    'data' => '',
                ]
            );
        }
    }
}
