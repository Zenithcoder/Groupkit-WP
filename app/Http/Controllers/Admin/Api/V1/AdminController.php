<?php

namespace App\Http\Controllers\Admin\Api\V1;

use App\Http\Controllers\Admin\Traits\AdminControllerBehavior;
use App\Http\Controllers\Traits\GroupkitControllerBehavior;
use App\Mail\InviteUser;
use App\Plan;
use App\Services\SubscriptionService;
use App\User;
use Bugsnag\BugsnagLaravel\Facades\Bugsnag;
use Exception;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use App\EmailUpdateRequest;
use Stripe\Stripe;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Routing\ResponseFactory;
use App\FacebookGroups;
use Stripe\Exception\ApiErrorException;
use App\Subscriptions;
use App\ResetMonthlyApproval;

/**
 * The API end-point for maintaining Admin and managed API request & response
 *
 * Class AdminController
 *
 * @package App\Http\Controllers\Admin\Api\V1
 */
class AdminController extends Controller
{
    use GroupkitControllerBehavior;
    use AdminControllerBehavior;

    /**
     * Sets the validation rules for this controller
     */
    protected function init()
    {
        $this->ajaxValidatorRules['updateUserStatus'] = [
            'user_id' => 'required|integer|exists:users,id,deleted_at,NULL',
            'user_status' => 'required|integer|min:0|max:1',
        ];

        $this->ajaxValidatorRules['removeUser'] = [
            'user_id' => 'required|integer|exists:users,id,deleted_at,NULL',
        ];

        $this->ajaxValidatorRules['getUserDetails'] = [
            'user_id' => 'required|integer|exists:users,id',
        ];

        $this->ajaxValidatorRules['updateUsersPassword'] = [
            'user_id' => 'required|integer|exists:users,id',
            'password' => 'required|string|min:8',
        ];

        $this->ajaxValidatorRules['addTeamMember'] = [
            'owner_id' => ['required', 'numeric', 'exists:users,id'],
            'name' => ['required', 'string', 'max:32', 'regex:/^[a-zA-Z\s\-\'\,]+$/u'],
            'email' => 'required|string|email|max:100',
            'facebook_groups_id' => ['array'],
        ];

        $this->ajaxValidatorRules['createUser'] = [
            'firstName' => 'required|string|max:127',
            'lastName'  => 'required|string|max:127',
            'email'     => 'required|email|unique:users,email',
            'plan'      => ['required', Rule::in(Plan::STRIPE_FREE_PLAN_TITLES)],
        ];

        $this->ajaxValidatorRules['getSubscriptions'] = [
            'limit'          => 'required|integer|min:0|max:100',
            'starting_after' => 'nullable|string',
        ];

        $this->ajaxValidatorRules['getApproveMembersCount'] = [
            'users' => 'required',
        ];

        $this->ajaxValidatorRules['resetMonthlyApproval'] = [
            'user_id' => 'required|integer|exists:users,id',
        ];

        $this->ajaxValidatorRules['sendNewEmailActivationLink'] = [
            'user_id' => 'required|integer|exists:users,id',
            'email' => 'required|string|max:191|email|unique:users,email',
        ];
    }

    /**
     * Gets details of all the users with their plan details and approvals
     *
     * @return HttpResponse if the get users success then return message with data, otherwise an error message
     */
    public function getUsersList(): HttpResponse
    {
        try {
            $users = app(User::class)->getUsersDetails($this->request);
        } catch (Exception $e) {
            Bugsnag::notifyException($e);
            return response(['message' => 'Invalid Request'], Response::HTTP_BAD_REQUEST);
        }

        return response([
            'message' => 'List of users',
            'data'    => $this->encrypt(['user' => $users]),
        ]);
    }

    /**
     * Updates user status in the user table
     *
     * @return Application|ResponseFactory|\Illuminate\Http\Response containing successful message if the
     * user status update success otherwise, an error message
     */
    public function updateUserStatus()
    {
        return response(app(User::class)->updateUserStatus($this->request));
    }

    /**
     * removes user from users table
     *
     * @return Application|ResponseFactory|\Illuminate\Http\Response containing successful message if the
     * user removed success, otherwise an error message
     */
    public function removeUser()
    {
        return response(User::deleteUser($this->request->user_id));
    }

    /**
     * Gets user details as per passed parameters (user_id) from the users table.
     *
     * @return HttpResponse with single user details based on user id from users table.
     *
     * @throws ApiErrorException upon a problem connecting with Stripe
     */
    public function getUserDetails(): HttpResponse
    {
        /* find the user from User table */
        $user = User::find($this->request->user_id);
        $membersList = User::getAssociatedTeamMembersList($this->request->user_id);
        $groups = User::getUserGroupDetails($user, $this->request);

        /* get users plan details */
        $activePlanDetails = app(User::class)->getSubscriptionDetails($user->stripe_id);
        $groupLimit = 0;
        $membersLimit = 0;

        if ($activePlanDetails) {
            $activePlanDetails->stripe_status = Subscriptions::SUBSCRIPTION_STATUSES[$activePlanDetails->stripe_status];
            /* get users active groups details */
            $activeGroups = FacebookGroups::where('user_id', $this->request->user_id)
                ->whereBetween(
                    'created_at',
                    [$activePlanDetails->current_period_start, $activePlanDetails->current_period_end]
                )
                ->count();

            $plan = Plan::getPlan($activePlanDetails->stripe_plan, ['product']);
            $groupLimit = $plan->product->metadata->group_limit ?? null;
            $membersLimit = $plan->product->metadata->members_limit ?? null;
            $approvalsMonthlyCount = User::getMembersApprovals(
                $this->request->user_id,
                $activePlanDetails->current_period_start,
                $activePlanDetails->current_period_end
            );
        }

        return response([
            'message' => 'User details.',
            'data' => $this->encrypt([
                'user' => $user,
                'memberList' => $membersList,
                'groups' => $groups,
                'planDetails' => $activePlanDetails,
                'activeGroups' => $activeGroups ?? 0,
                'approvalsMonthlyCount' => $approvalsMonthlyCount ?? 0,
                'groupLimit' => $groupLimit,
                'membersLimit' => $membersLimit,
            ]),
        ]);
    }

    /**
     * Resets user password
     *
     * @return Application|ResponseFactory|\Illuminate\Http\Response if Password is changed successfully then
     * return message, otherwise returns an error message.
     */
    public function updateUsersPassword()
    {
        $user = User::find($this->request->user_id);
        $user->password = Hash::make(trim($this->request->password));
        $user->update();

        return response(['message' => 'User Details Updated Successfully.']);
    }

    /**
     * Adds team members to users account.
     *
     * @return Application|ResponseFactory|\Illuminate\Http\Response containing successful message if the
     * user added as team member successfully, otherwise returns error message.
     */
    public function addTeamMember()
    {
        if (!app(User::class)->find($this->request->owner_id)->canAddTeamMembers()) {
            return response(
                ['message' => 'The owner has reached the limit of the adding new team members'],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }

        $user = app(User::class)->addTeamMember(
            $this->request->owner_id,
            $this->request->only(['email', 'name', 'facebook_groups_id'])
        );

        return response(
            ['message' => $user['message']],
            $user['success'] ? Response::HTTP_OK : Response::HTTP_BAD_REQUEST
        );
    }

    /**
     * Creates user with free subscription
     *
     * @return HttpResponse with success message if user is created and subscription for that user,
     *                      otherwise error message
     */
    public function createUser(): HttpResponse
    {
        try {
            DB::beginTransaction();
            Stripe::setApiKey(config('services.stripe.default.secret'));
            $user = User::create([
                'email' => $this->request->email,
                'name' => "{$this->request->firstName} {$this->request->lastName}"
            ]);

            $user = app(SubscriptionService::class)->createCustomer($user);
            app(SubscriptionService::class)->subscription($user, Plan::STRIPE_PLAN_IDS['default'][$this->request->plan]);
            Mail::to($user->email)->send(new InviteUser($user));
            DB::commit();
        } catch (Exception $e) {
            Bugsnag::notifyException($e);
            logger()->error($e->getMessage());
            DB::rollBack();

            return response(
                ['message' => 'Server error'],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }

        return response(['message' => 'Successfully created new user']);
    }

    /**
     * get all customers subscription details
     *
     * @return HttpResponse with success message if subscriptions data get successfully,
     */
    public function getSubscriptions(): HttpResponse
    {
        $response = app(SubscriptionService::class)->subscriptionList(
            $this->request->limit,
            $this->request->input('starting_after')
        );

        if ($response['success']) {
            return response([
                'message' => $response['message'],
                'data' => $this->encrypt([
                    'subscriptionsList' => $response['subscriptions'],
                ]),
            ]);
        }

        return response(['message' => $response['message']]);
    }

    /**
     * used to get total members approval count
     *
     * @return HttpResponse with success message if members approval count get successfully,
     */
    public function getApproveMembersCount(): HttpResponse
    {
        return response([
            'message' => 'List of members\' counts',
            'data' => $this->encrypt([
                'member_count' => User::getTotalApproveMembersCount(
                    $this->request->users
                ),
            ]),
        ]);
    }

    /**
     * Resets monthly approvals limit
     *
     * @return HttpResponse with success message if user is created and subscription for that user, otherwise error
     * message
     */
    public function resetMonthlyApproval(): HttpResponse
    {
        ResetMonthlyApproval::create($this->request->only('user_id'));

        return response(['message' => 'Monthly approvals limit reset successfully.']);
    }

    /**
     * Sends Activation Link to the newly requested email address
     *
     * @return HttpResponse with the message and response status with code
     * {@see Response::HTTP_OK} if everything is okay, otherwise {@see Response::HTTP_BAD_REQUEST}
     */
    public function sendNewEmailActivationLink()
    {
        Stripe::setApiKey(config('services.stripe.default.secret'));
        $user = User::find($this->request->user_id);

        $request = app(EmailUpdateRequest::class)->sendActivationLink(
            $user->email,
            $this->request->email,
            $this->request->getClientIp(),
        );

        return response(
            ['message' => $request['message']],
            $request['success'] ? Response::HTTP_OK : Response::HTTP_BAD_REQUEST
        );
    }
}
