<?php

namespace App;

use Bugsnag\BugsnagLaravel\Facades\Bugsnag;
use Exception;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Laravel\Passport\HasApiTokens;
use Laravel\Cashier\Billable;
use App\Notifications\CustomVerifyEmail;
use App\Notifications\VerifyUserMail;
use Illuminate\Support\Facades\Hash;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Carbon\Carbon;
use Laravel\Socialite\Facades\Socialite;
use Illuminate\Support\Str;
use Stripe\Customer;
use Stripe\Exception\ApiErrorException;
use App\Services\SubscriptionService;
use App\Mail\TeamMemberMail;
use Illuminate\Support\Facades\Mail;
use Stripe\Product;
use Stripe\Stripe;
use Stripe\StripeClient;
use Stripe\StripeObject;
use Illuminate\Support\Facades\Password;
use Stripe\Subscription;
use App\Subscriptions;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens;
    use Notifiable;
    use Billable;
    use HasFactory;
    use SoftDeletes;

    /** @var int User status value for active users */
    public const STATUS_ACTIVE = 1;

    /** @var int User status value for inactive users */
    public const STATUS_INACTIVE = 0;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'ref_code',
        'stripe_account',
        'stripe_id',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password', 'remember_token',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    /**
     * All statuses that indicate that a subscription is currently active.
     *
     * @var array
     */
    public const STRIPE_ACTIVE_STATUSES = [
        'trialing' => 'trialing',
        'active' => 'active',
    ];

    /**
     * The possible response statuses when creating or updating a user.
     * The keys are used as a unique string to selecting needed response status
     * The values are used as a response status message
     */
    public const RESPONSE_STATUSES = [
        'STRIPE_ID_ALREADY_EXISTS' => 'Stripe ID already exists.',
    ];

    /**
     * Send verification email to the user.
     */
    public function sendEmailVerificationNotification()
    {
        $this->notify(new VerifyUserMail($this->name));
    }

    /**
     * Many to many relationship between `users` and `facebook_groups` tables
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function teamMemberGroupAccess()
    {
        return $this->belongsToMany(
            FacebookGroups::class,
            'team_member_group_access',
            'user_id',
            'facebook_group_id'
        );
    }

    /**
     * One to many relationship between `users` and `facebook_groups` tables
     *
     * @return HasMany groups that user own
     */
    public function groupsOwned()
    {
        return $this->hasMany(FacebookGroups::class);
    }


    /**
     * Relationship between `facebook_groups`, `group_settings` and `users` table
     *
     * @return BelongsToMany instance with column_visibility and columns_width
     */
    public function groupSettings(): BelongsToMany
    {
        return $this->belongsToMany(
            FacebookGroups::class,
            'group_settings',
            'user_id',
            'group_id'
        )
            ->as('groupSettings')
            ->withPivot([
                'columns_visibility',
                'columns_width',
            ]);
    }

    /**
     * Determine whether this user has an active subscription
     *
     * @param ?string $customerId
     *
     * @return int
     */
    public function hasSubscription(?string $customerId): int
    {
        return self::getSubscriptionDetails($customerId) ? 1 : 0;
    }

    /**
     * Returns customer's Stripe subscription
     *
     * @param string $stripeId of the Stripe customer for getting his subscription
     *
     * @return ?Subscription from the Stripe or null if there is no subscription for this customer
     */
    public static function getCustomerSubscription(string $stripeId): ?Subscription
    {
        Stripe::setApiKey(static::getStripeSecret($stripeId));
        $customer = app(Customer::class)->retrieve($stripeId);

        if (!$customer->subscriptions || !$customer->subscriptions->data) {
            return null;
        }

        return $customer->subscriptions->data[0];
    }

    public function activePlanDetails()
    {
        return self::getSubscriptionDetails(auth()->user()->stripe_id);
    }

    /**
     * Determines whether the user has an active plan
     *
     * @return bool represents whether the user's plan is active or not
     */
    public function activePlan(): bool
    {
        $subscription = self::getSubscriptionDetails($this->stripe_id);

        if (is_null($subscription)) {
            return false;
        }

        if ($subscription->stripe_status == self::STRIPE_ACTIVE_STATUSES['trialing']) {
            return strtotime($subscription->trial_ends_at) >= time();
        }

        return ($subscription->trial_ends_at == null && $subscription->ends_at)
            ? strtotime($subscription->ends_at) >= time()
            : strtotime($subscription->current_period_end) >= time()
        ;
    }

    public static function subscriptionsPlan($id)
    {
        $user = self::find($id);
        if (@$user->stripe_id) {
            $subscription = self::getSubscriptionDetails($user->stripe_id);
            if ($subscription) {
                return $subscription->name;
            }
        }
        return 'N/A';
    }

    /**
     * Gets how many sub users owner has belong to his account
     *
     * @return int representing the total number of members
     * (a.k.a. group moderators/sub-users/assistants) on this owner's team
     */
    public function getTotalTeamMemberCount(): int
    {
        return OwnerToTeamMember::where('owner_id', $this->id)->count();
    }

    public static function getDetailsByUser($user)
    {
        /** user */
        $user->plan_name = self::subscriptionsPlan($user->id);
        $user->access_team = (bool)$user->activePlan();

        $userDetails = new \stdClass();

        // @TODO: Remove in totality this access_group from cloud and extension
        // Access control is the sole responsibility of the API - currently in the ScrapingController
        // $teamAccess = $user->teamMemberGroupAccess()->select('fb_id')->get();
        $userDetails->access_group = /* $teamAccess->isNotEmpty() ? $teamAccess :*/ 'all';

        $userDetails->id = $user->id;
        $userDetails->email = $user->email;
        $userDetails->name = $user->name;
        $userDetails->plan_name = $user->plan_name;
        $userDetails->access_team = $user->access_team;

        return $userDetails;
    }

    /**
     * Determines if a user has reached their import limit for groups or members
     *
     * @param string $itemType Determines at which level to check limits which can either be
     *                         'group' or 'member'. If 'group' then the facebook_groups table is used,
     *                          otherwise, the group_members table will be used
     *
     * @return bool true if the another specified entity can be added for this user, otherwise false
     *
     * @throws ApiErrorException upon a problem connecting with Stripe
     */
    public function canAddAnother(string $itemType): bool
    {
        return (bool) $this->getAvailableCountFor($itemType . 's');
    }

    /**
     * Gets the count of additional groups or members that can be added
     *
     * @param string $itemType Determines at which level to check limits which can either be
     *                         'groups' or 'members'. If 'groups' then the facebook_groups table is used,
     *                          otherwise, the group_members table will be used
     *
     * @return int the total number of groups or members that can still be added by the current user. If the user
     *             has an unlimited account, then the maximum integer value supported by PHP is returned
     *
     * @throws ApiErrorException upon a problem connecting with Stripe
     */
    public function getAvailableCountFor(string $itemType): int
    {
        $planData = app(User::class)->getSubscriptionDetails(auth()->user()->stripe_id);

        if (@$planData->stripe_plan == Plan::STRIPE_PLAN_IDS[auth()->user()->stripe_account]['BASIC']) {
            if (!$planData->current_period_start && !$planData->current_period_end) {
                return 0;
            }

            $plan = Plan::getPlan($planData->stripe_plan, ['product']);
            $startDate = date('Y-m-d', strtotime($planData->current_period_start));
            $endDate = date('Y-m-d', strtotime($planData->current_period_end));

            if ($itemType === 'groups' && isset($plan->product->metadata->group_limit)) {
                $groupCount = FacebookGroups::where('user_id', $this->id)
                    ->whereBetween('created_at', [$startDate, $endDate])
                    ->count();

                return  max($plan->product->metadata->group_limit - $groupCount, 0);
            } elseif ($itemType === 'members' && isset($plan->product->metadata->members_limit)) {
                $approvalsMonthlyCount = self::getMembersApprovals(
                    $this->id,
                    $planData->current_period_start,
                    $planData->current_period_end
                );

                return  max($plan->product->metadata->members_limit - $approvalsMonthlyCount, 0);
            }
        }

        return PHP_INT_MAX;
    }

    /**
     * Register user for application.
     *
     * @param Request $request The current request with data for new {@see User}
     *
     * @return User logged in the application
     */
    public static function createUser(Request $request)
    {
        $user = new User();
        try {
            $socialToken = $request->session()->get('access_token');
            $socialProvider = $request->session()->get('access_provider');
            $isSocialLogin = $socialToken && $socialProvider;

            $user = User::create(
                [
                    'name'      => $request->firstName . ' ' . $request->lastName,
                    'email'     => $request->email,
                    'password'  => $isSocialLogin ? Hash::make(Str::random()) : Hash::make($request->password),
                    'ref_code'  => Cookie::get('tapfiliate_id'),
                ]
            );

            if ($isSocialLogin) {
                $socialUserData = Socialite::driver($socialProvider)->userFromToken($socialToken);
                $user->setAttribute('status', self::STATUS_ACTIVE)
                    ->setAttribute(User::getUserIdFieldForSocialProvider($socialProvider), $socialUserData->id)
                    ->setAttribute(User::getAccessTokenFieldForSocialProvider($socialProvider), $socialUserData->token)
                    ->markEmailAsVerified(); // no need to verify, it is verified by the social login provider
            } else {
                $user->sendEmailVerificationNotification();
            }

            $user->save();
            Auth::loginUsingId($user->id);

            return $user;
        } catch (Exception $e) {
            Bugsnag::notifyException(
                $e,
                function ($report) use ($user) {
                    $report->setMetaData([
                        'New User' => $user->toArray()
                    ]);
                }
            );
            return $user;
        }
    }

    /**
     * This function used for get subscription, when we passed the customer_id
     *
     * @param string $customerId from User table
     *
     * @return \stdClass|null
     */
    public function getSubscription(string $customerId)
    {
        return self::getSubscriptionDetails($customerId);
    }

    /**
     * Gets stripe secret for customer with provided stripe id
     *
     * @param string|null $stripeId of the GroupKit user
     *
     * @return string stripe secret for the logged in user
     */
    public static function getStripeSecret(?string $stripeId): string
    {
        if (session('stripe_secret')) {
            return session('stripe_secret');
        }

        $customer = User::where('stripe_id', $stripeId)->first();

        $stripeSecret = ($customer && $customer->stripe_account === 'new')
            ? config('services.stripe.new.secret')
            : config('services.stripe.default.secret');

        session(['stripe_secret' => $stripeSecret]);

        return $stripeSecret;
    }

    /**
     * Gets stripe publishable key for customer with provided stripe id
     *
     * @param string|null $stripeId of the GroupKit user
     *
     * @return string stripe publishable key for the logged in user
     */
    public static function getStripePublishKey(?string $stripeId): string
    {
        if (session('stripe_publish_key')) {
            return session('stripe_publish_key');
        }

        $customer = User::where('stripe_id', $stripeId)->first();

        $stripePublishableKey = ($customer && $customer->stripe_account === 'new')
            ? config('services.stripe.new.key')
            : config('services.stripe.default.key');

        session(['stripe_publish_key' => $stripePublishableKey]);

        return $stripePublishableKey;
    }

    /**
     * This function used to get subscription details.
     *
     * @param ?string $customerId
     *
     * @return ?\stdClass $subscription;
     */
    public static function getSubscriptionDetails(?string $customerId = null): ?\stdClass
    {
        $subscription = new \stdClass();

        try {
            if ($customerId) {
                // set Stripe API key
                Stripe::setApiKey(static::getStripeSecret($customerId));

                $customer = app(Customer::class)->retrieve($customerId);
                if ($customer->subscriptions && @$customer->subscriptions->data[0]) {
                    $stripeSubscription = $customer->subscriptions->data[0];

                    $subscription->stripe_id = $stripeSubscription->id;
                    $subscription->stripe_status = $stripeSubscription->status;
                    $subscription->stripe_plan = $stripeSubscription->plan->id;
                    $subscription->name = app(Product::class)->retrieve($stripeSubscription->plan->product)->name;
                    $subscription->current_period_start = Carbon::parse($stripeSubscription->current_period_start)
                                                            ->format('Y-m-d H:i:s');
                    $subscription->current_period_end = Carbon::parse($stripeSubscription->current_period_end)
                                                            ->format('Y-m-d H:i:s');
                    $subscription->ends_at = $stripeSubscription->cancel_at ?
                        Carbon::parse($stripeSubscription->cancel_at)->format('Y-m-d H:i:s') :
                        null;
                    $subscription->trial_ends_at = $stripeSubscription->trial_end ?
                        Carbon::parse($stripeSubscription->trial_end)->format('Y-m-d H:i:s') :
                        null;
                    $subscription->quantity = $stripeSubscription->quantity;
                }
            }
            return count((array)$subscription) ? $subscription : null;
        } catch (Exception $e) {
            Bugsnag::notifyException($e);
            return count((array)$subscription) ? $subscription : null;
        }
    }

    /**
     * Generates user id field for a Social Login provider
     *
     * @param string $provider of the Social Login authentication
     *
     * @return string field name used to store the Social Login user id for given provider
     *
     * @throws InvalidArgumentException if the Social Login provider is not supported
     */
    public static function getUserIdFieldForSocialProvider(string $provider): string
    {
        if (!Http\Controllers\Auth\SocialController::isProviderSupported($provider)) {
            throw new InvalidArgumentException('Unsupported social provider');
        }
        return strtolower($provider) . '_user_id';
    }

    /**
     * Generates access token field for a Social Login provider
     *
     * @param string $provider of the Social Login authentication
     *
     * @return string field name used to store the Social Login access token for given provider
     *
     * @throws InvalidArgumentException if the Social Login provider is not supported
     */
    public static function getAccessTokenFieldForSocialProvider(string $provider): string
    {
        if (!Http\Controllers\Auth\SocialController::isProviderSupported($provider)) {
            throw new InvalidArgumentException('Unsupported social provider');
        }
        return strtolower($provider) . '_access_token';
    }

    /**
     * Determines if a user has reached their limit for adding additional team members
     *
     * @return bool true if can add team members otherwise false
     *
     * @throws ApiErrorException if the {@see \Stripe\ApiRequestor::request} fails
     */
    public function canAddTeamMembers(): bool
    {
        $plan = $this->getActiveStripePlan();

        return isset($plan->product->metadata->moderator_limit)
            ? $this->getTotalTeamMemberCount() < (int)$plan->product->metadata->moderator_limit
            : true;
    }

    /**
     * Returns subscribed stripe plan with a product of the user
     *
     * @return \Stripe\Plan that belongs to the user
     *
     * @throws ApiErrorException if the {@see \Stripe\ApiRequestor::request} fails
     */
    public function getActiveStripePlan()
    {
        $planData = self::getSubscriptionDetails($this->stripe_id);

        return Plan::getPlan($planData->stripe_plan, ['product']);
    }

    /**
     * Determines if the user can access the provided group
     *
     * @param int $id of the {@see FacebookGroups}
     * @return bool true if user can access group, otherwise false
     */
    public function canAccessGroup(int $id)
    {
        $groupsOwned = FacebookGroups::where('id', $id)
            ->where('user_id', $this->id)
            ->first();

        $groupsModerated = TeamMemberGroupAccess::where('facebook_group_id', $id)
            ->where('user_id', $this->id)
            ->first();

        return $groupsOwned || $groupsModerated;
    }

    /**
     * Determine if the user owns the provided Facebook Group by it's Facebook ID
     *
     * @param string $groupFacebookId Facebook ID of the group
     *
     * @return FacebookGroups|Model|null Facebook group object if found, otherwise null
     */
    public function getOwnedGroupByFacebookId(string $groupFacebookId)
    {
        return $this->groupsOwned()->where('fb_id', $groupFacebookId)->first();
    }

    /**
     * get details of all the users with their plan details and approvals
     *
     * @param Request $request for decrypting request data
     *
     * @return object users record
     */
    public static function getUsersDetails(Request $request): object
    {
        $offset = $request->offset ?? 0;
        $limit = $request->limit ?? 10;

        $users = User::whereNotNull('stripe_id');
        /** filter email **/
        if (@$request->email) {
            $users->where('email', 'like', '%' . $request->email . '%');
        }
        /** filter status **/
        if (!@$request->status && $request->filled('status')) {
            $users->where('status', $request->status);
        }
        /** filter customers **/
        if (@$request->customers_id_list) {
            $stripeWhereInMethod = ($request->searchWithStripeKeywords) ? 'whereIn' : 'orWhereIn';
            $users->$stripeWhereInMethod('stripe_id', $request->customers_id_list);
        }
        /** search value **/
        if ($request->search) {
            $searchValue = $request->search;
            $users->where(function ($query) use ($searchValue, $request) {
                $stripeWhereInMethod = ($request->searchWithStripeKeywords) ? 'whereIn' : 'orWhereIn';
                $query->where('name', 'LIKE', '%' . $searchValue . '%')
                    ->orWhere('email', 'LIKE', '%' . $searchValue . '%')
                    ->orWhereRaw('DATE_FORMAT(created_at, "%m/%d/%Y") like ?', ['%' . $searchValue . '%'])
                    ->$stripeWhereInMethod('stripe_id', $request->customers_id_list);
            });
        }

        /** sort a column */
        if (@$request->order_column && @$request->order_by) {
            $users->orderBy($request->order_column, $request->order_by);
        } else {
            $users->orderByDesc('created_at');
        }

        return $users->paginate($limit, '*', 'page', $offset);
    }

    /**
     * update user status in user table
     *
     * @param Request $request for passed the decrypt request data
     *
     * @return array containing successful message if the user status update success, otherwise an error message
     */
    public function updateUserStatus(Request $request): array
    {
        $user = self::find($request->user_id);
        $user->status = $request->user_status;
        app(SubscriptionService::class)->recurringPayment($user);
        $user->save();

        return ['message' => $user->status ? 'User activated' : 'User deactivated'];
    }

    /**
     * Removes the user from the 'users' table and cancel user's stripe subscription
     *
     * @param int $userId of the user that needs to be deleted
     *
     * @return array containing successful message if the user removed success, otherwise an error message
     */
    public static function deleteUser(int $userId): array
    {
        /* find the user from User table */
        $user = self::find($userId);
        /** retrieve subscription after cancelled  */
        app(SubscriptionService::class)->cancel($user);
        $user->delete();

        return ['message' => 'User deleted successfully'];
    }

    /**
     * Gets all team members of the owner's team
     *
     * @param integer $ownerId for a owner of team members
     *
     * @return object User for a associated team members list
     */
    public static function getAssociatedTeamMembersList(int $ownerId): object
    {
        $teamMemberIds = DB::table('owner_to_team_members')
            ->where('owner_id', $ownerId)
            ->pluck('team_member_id');

        return User::with('teamMemberGroupAccess')
            ->whereIn('id', $teamMemberIds)
            ->orderByDesc('id')
            ->select('users.id', 'users.name', 'users.email', (DB::raw('"********" AS dummyPassword')))
            ->get();
    }

    /**
     * Gets all disabled groups for the user
     *
     * @param int $userId id of the current user.
     *
     * @return object disabled facebook groups numeric facebook ids (fb_ids).
     */
    public static function getDisabledGroups(int $userId): object
    {
        return DB::table('disabled_groups')
            ->where('user_id', $userId)
            ->pluck('facebook_group_fb_id');
    }

    /**
     * Returns count of active group members based on ownerId & groupId from the group_members table.
     *
     * @param int $ownerId for a owner of team members
     * @param int $groupId to fetch the count of the group
     *
     * @return int value which shows total members of the group in passed group id.
     */
    public static function getGroupMembersCount(int $ownerId, int $groupId): int
    {
        return DB::table('group_members')
            ->whereNull('deleted_at')
            ->where('group_id', $groupId)
            ->where('user_id', $ownerId)
            ->where('is_approved', 1)
            ->select('group_members.id')
            ->count();
    }

    /**
     * Gets the approvals count for Facebook group members between subscription start date and subscription end date.
     *
     * @param int $ownerId having parent user id
     * @param string $subscriptionStartDate for stripe active plan startDate
     * @param string $subscriptionEndDate for stripe active plan endDate
     *
     * @return integer value of monthly approvals of users.
     */
    public static function getMembersApprovals(
        int $ownerId,
        string $subscriptionStartDate,
        string $subscriptionEndDate
    ): int {
        $startDate = date('Y-m-d', strtotime($subscriptionStartDate));
        $endDate = date('Y-m-d', strtotime($subscriptionEndDate));
        /* Gets Latest subscription start date & end date based on reset_monthly_approvals tables request. */
        $getSubscriptionStartDate = ResetMonthlyApproval::select('created_at')
            ->where('user_id', $ownerId)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->orderByDesc('created_at')
            ->first();
        $updatedStartDate = @$getSubscriptionStartDate->created_at ?? $startDate;
        $formattedStartDate = Carbon::parse($updatedStartDate)->format('Y-m-d');

        return DB::table('group_members')
            ->join('facebook_groups', 'group_members.group_id', '=', 'facebook_groups.id')
            ->where('facebook_groups.user_id', $ownerId)
            ->where('group_members.is_approved', 1)
            ->whereBetween('group_members.date_add_time', [$formattedStartDate, $endDate])
            ->select('group_members.id')
            ->count();
    }

    /**
     * Gets User group related information
     *
     * @param object $user from users table
     * @param object $requestData having request parameters
     *
     * @return object of group details
     */
    public static function getUserGroupDetails(object $user, object $requestData): object
    {
        $groups = $user->teamMemberGroupAccess->merge($user->groupsOwned);

        foreach ($groups as $group) {
            $group->totalGroupMembersApprovals = self::getGroupMembersCount($requestData->user_id, $group->id);
            $group->groupLink = config('const')['GROUP_LINK'] . $group->fb_id;
        }

        return $groups;
    }


    /**
     * Creates a user and adds that user as a team member to the owner team.
     *
     * @param integer $ownerId of parent user
     * @param array $teamMemberData contains name,email,facebook_groups_id
     *
     * @return array containing successful message if the
     * user added as team member successfully, otherwise returns error message.
     */
    public function addTeamMember(int $ownerId, array $teamMemberData): array
    {
        $owner = self::find($ownerId);
        $teamMember = User::where('email', $teamMemberData['email'])->first();

        if ($teamMember) {
            $ownerToTeamMember = OwnerToTeamMember::where('owner_id', $owner->id)
                ->where('team_member_id', $teamMember->id)
                ->count();
            if ($ownerToTeamMember) {
                return [
                    'message' => 'The member already exists in your team.',
                    'success' => false,
                ];
            }
        }

        try {
            DB::beginTransaction();

            if (!$teamMember) {
                $teamMember = User::create($teamMemberData);
            }
            /* Managing record on owner_to_team_members table */
            $ownerToTeamMember = OwnerToTeamMember::create([
                'owner_id' => $owner->id,
                'team_member_id' => $teamMember->id,
            ]);
            $teamMemberAssignments = [];
            foreach ($teamMemberData['facebook_groups_id'] ?? [] as $facebookGroupId) {
                $teamMemberAssignments[$facebookGroupId] = [
                    'owner_to_team_member_id' => $ownerToTeamMember->id,
                ];
            }
            /* Managing record as per groups id on team_member_group_access table */
            $teamMember->teamMemberGroupAccess()->attach($teamMemberAssignments);

            DB::commit();
        } catch (Exception $e) {
            Bugsnag::notifyException($e);
            DB::rollBack();
            logger()->error($e->getMessage());
            return [
                'message' => 'Unable To Add Team Member.',
                'success' => false,
            ];
        }

        $this->sendInvitationMailToTeamMember($owner, $teamMember);

        return [
            'message' => 'Invite sent to team member successfully.',
            'success' => true,
        ];
    }

    /**
     * Sends invitation email {@see \App\Mail\TeamMemberMail} to the team member
     *
     * @param User $owner to which team will be added $teamMember
     * @param User $teamMember to which e-mail address we will send the invitation email
     */
    public function sendInvitationMailToTeamMember(User $owner, User $teamMember): void
    {
        /* Sending invitation mail to newly join member */
        Mail::to($teamMember->email)->send(
            new TeamMemberMail([
                'acc_holder_name' => $owner->name,
                'name' => $teamMember->name,
                'user_name' => $teamMember->email,
                'token' => Password::createToken($teamMember),
            ])
        );
    }

    /**
     * used to get total members approval count for all passed users
     *
     * @param array $users passed as multidimensional users containing
     * three keys {period_end, period_end, user_id},
     *
     * @return array Response for total member count
     */
    public static function getTotalApproveMembersCount(array $users): array
    {
        return array_map(function ($user) {
            if (@$user['user_id'] && @$user['period_start'] && @$user['period_end']) {
                $user['count'] = self::getMembersApprovals(
                    $user['user_id'],
                    $user['period_start'],
                    $user['period_end']
                );
            }

            return $user;
        }, $users);
    }

    /**
     * Gets card data from the Stripe default payment method
     *
     * @param ?string $paymentMethod represents the customer's default payment
     *                              and holds card data (last4, brand, expire month/year)
     *
     * @return StripeObject with card brand, expiration month/year of the card
     *
     * @throws ApiErrorException upon a problem connecting with Stripe
     */
    public function getCard(?string $paymentMethod): StripeObject
    {
        $stripe = new StripeClient(User::getStripeSecret(auth()->user()->stripeId()));

        $paymentMethod = $stripe->paymentMethods->retrieve($paymentMethod);

        return $paymentMethod->card;
    }

    /**
     * Cancels all existing subscription for current user
     */
    public function cancelExistingSubscriptions()
    {
        Stripe::setApiKey(User::getStripeSecret($this->stripeId()));
        app(SubscriptionService::class)->cancel($this);
    }

    /**
     * Determines if a user has Pro plan or not
     *
     * @return bool true if user has pro plan, otherwise false
     */
    public function hasProPlan(): bool
    {
        $stripePlanId = $this->getStripePlanId();

        return $stripePlanId === Plan::STRIPE_PLAN_IDS[$this->stripe_account]['PRO_MONTHLY']
            || $stripePlanId === Plan::STRIPE_PLAN_IDS[$this->stripe_account]['PRO_ANNUAL']
            || $stripePlanId === Plan::STRIPE_PLAN_IDS[$this->stripe_account]['FREE_PRO'];
    }

    /**
     * Determines if a user has Basic plan or not
     *
     * @return bool true if user has Basic plan, otherwise false
     */
    public function hasBasicPlan(): bool
    {
        $stripePlanId = $this->getStripePlanId();

        return $stripePlanId === Plan::STRIPE_PLAN_IDS[$this->stripe_account]['BASIC']
            || $stripePlanId === Plan::STRIPE_PLAN_IDS[$this->stripe_account]['FREE_BASIC'];
    }

    /**
     * Determines if a user has any subscription plan
     *
     * @return bool true if user does not have any subscription plan, otherwise false
     */
    public function planIsNotAvailable(): bool
    {
        $stripePlanId = $this->getStripePlanId();

        if (!$stripePlanId) {
            return true;
        }

        return !in_array($stripePlanId, array_values(Plan::STRIPE_PLAN_IDS[$this->stripe_account]));
    }

    /**
     * Gets user's Stripe plan id
     *
     * @return string|null Stripe plan id
     */
    public function getStripePlanId(): ?string
    {
        $subscriptionDetails = self::getSubscriptionDetails($this->stripe_id);

        if (!$subscriptionDetails) {
            return null;
        }

        return $subscriptionDetails->stripe_plan;
    }

    /**
     * Returns group id of most recently added members from the group_members table.
     *
     * @param int $userId for an owner of team members
     *
     * @return string value which shows most recently added members group id.
     */
    public static function getGroupIdWithMostRecentlyAddedMember(int $userId): string
    {
        $groupWithMostRecentlyAddedMember = DB::table('group_members')
            ->join('facebook_groups', 'group_members.group_id', '=', 'facebook_groups.id')
            ->whereNull('facebook_groups.deleted_at')
            ->whereNull('group_members.deleted_at')
            ->where('group_members.user_id', $userId)
            ->where('facebook_groups.user_id', $userId)
            ->orderByDesc('group_members.created_at')
            ->orderByDesc('group_members.group_id')
            ->first();

        return ($groupWithMostRecentlyAddedMember->group_id) ?? '';
    }

    /**
     * Determines if subscription is paused and provided pause type matches the Stripe subscription pause type
     *
     * @param int $userId of the customer to check his subscription
     * @param string $pauseType to determine is Stripe subscription pause type equal to.
     *                          It can be one of the {@see Subscriptions::PAUSE_TYPES}
     *
     * @return bool if subscription is paused and pause type is the same as provided $pauseType
     */
    public static function subscriptionIsPaused(int $userId, string $pauseType): bool
    {
        $user = User::find($userId);
        $isPaused = false;

        if ($user && $user->hasStripeId()) {
            $subscription = User::getCustomerSubscription($user->stripeId());

            $isPaused = $subscription
                && $subscription->pause_collection
                && $subscription->pause_collection->behavior === $pauseType;
        }

        return $isPaused;
    }

    /**
     * Determines if subscription is paused and provided pause type is 'SUSPEND_SERVICE'
     *
     * @param int $userId of the customer to check his subscription
     *
     * @return bool true if subscription is paused and provided pause type is 'SUSPEND_SERVICE', otherwise false
     */
    public static function subscriptionIsPausedForSuspendedService(int $userId): bool
    {
        return self::subscriptionIsPaused($userId, Subscriptions::PAUSE_TYPES['SUSPEND_SERVICE']);
    }

    /**
     * A user can have the different settings of columns for the different groups
     * on the UI on the front end. So this is a many to many relationship.
     *
     * @return BelongsToMany a group can have many owners or team members
     * that can be logged into the application.
     */
    public function groupColumnsSettings(): BelongsToMany
    {
        return $this->belongsToMany(
            FacebookGroups::class,
            'group_settings',
            'user_id',
            'group_id'
        )->as('columnsVisibility')->withPivot('columns_visibility');
    }

    /**
     * Finds a record for this user and group ID in 'group_settings'
     * table using 'groupColumnsSettings' relationship, and filters it
     * by group ID, since there can be more than one record for this user.
     *
     * @param int $groupId of the group on which there are some actions
     *                         from the front end side via UI (like columns
     *                         visibility settings).
     *
     * @return ?FacebookGroups model with the data from the 'group_settings'
     * pivot table, filtered by user ID and group ID if it finds it.
     * Otherwise null.
     */
    public function getColumnsSettingsByGroup(int $groupId): ?FacebookGroups
    {
        return $this->groupColumnsSettings()->where('group_id', $groupId)->first();
    }

    /**
     * Returns group settings by provided group id for the current user
     *
     * @param int $groupId of the {@see FacebookGroups} to search group settings for that group
     *
     * @return ?FacebookGroups model with group settings (columns_visibility and columns_settings)
     */
    public function getGroupSettings(int $groupId): ?FacebookGroups
    {
        return $this->groupSettings()->where('group_id', $groupId)->first();
    }
}
