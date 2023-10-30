<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Traits\GroupkitControllerBehavior;
use App\Jobs\Subscription\PauseSubscription;
use App\Plan;
use App\Services\AweberService;
use App\Services\SubscriptionService;
use App\Services\TapfiliateService;
use App\Subscriptions;
use App\User;
use Exception;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Stripe\Stripe;
use Stripe\Subscription;
use Bugsnag\BugsnagLaravel\Facades\Bugsnag;
use Carbon\Carbon;
use Stripe\SubscriptionSchedule;
use App\Jobs\RemoveUsersInactiveGroup;
use Illuminate\Contracts\Bus\Dispatcher;
use App\PrimaryGroup;
use Symfony\Component\HttpFoundation\Response;

class SubscriptionController extends Controller
{
    use GroupkitControllerBehavior;

    /**
     * It redirects the guest user to the upgrade plan page
     *
     * @return RedirectResponse to wait page with planId and collected subscription data
     */
    public function redirectToUpgradePlan(): RedirectResponse
    {
        $this->request->validate(
            [
                'first_name' => 'required|string|max:85',
                'last_name' => 'required|string|max:85',
                'email' => [
                    'required',
                    'string',
                    'max:100',
                    'email',
                    auth()->user()
                        ? Rule::unique('users', 'email')->ignore(auth()->id())
                        : 'unique:users,email'
                    ,
                ],
                'password' => session()->missing('access_token')
                    ? 'required|string|min:8|confirmed'
                    : 'nullable'
                ,
            ]
        );

        $sessionData = [
            'paymentMethod' => $this->request->input('paymentMethod'),
            'purchase' => $this->request->input('purchase') ? 'on' : 'off',
            'requestUser' => [
                'firstName' => $this->request->input('first_name'),
                'lastName' => $this->request->input('last_name'),
                'email' => $this->request->input('email'),
                'password' => $this->request->input('password'),
                'access_token' => $this->request->input('access_token'),
                'access_provider' => $this->request->input('access_provider'),
                'userData' => $this->request->input('userData'),
            ],
        ];

        return redirect()->route('wait')->with([
            'planId' => $this->request->input('plan'),
            'token' => base64_encode(json_encode($sessionData)),
        ]);
    }

    /**
     * Creates subscription based on provided period
     *
     * @param AweberService $aweberService used to add new users to the plan specific mailing list
     *
     * @return Application|RedirectResponse|View containing error message if exception is thrown
     *                                           or redirect to GroupKit thanks page if success
     */
    public function create(AweberService $aweberService)
    {
        try {
            Stripe::setApiKey(config('services.stripe.default.secret'));

            DB::beginTransaction();
            {
                $user = auth()->user() ?? app(User::class)->createUser($this->request);

                $user = app(SubscriptionService::class)->createCustomer($user, $this->request->paymentMethod);

                $plan = Plan::getPlan($this->request->plan, ['product']);

                $canUseTrial = !$user->hasSubscription($user->stripe_id);
                $trialDays = null;
                if ($plan->id !== Plan::STRIPE_PLAN_IDS['default']['PRO_ANNUAL']) {
                    $trialDays = (int)$plan->product->metadata->trialLength;
                }
                $trial = $canUseTrial && $trialDays;

                app(SubscriptionService::class)->subscription($user, $plan->id, $trial ? $trialDays : null);

                $subscription = app(User::class)->getSubscription($user->stripe_id);
                if ($user->ref_code) {
                    app(TapfiliateService::class)->createCustomer($user, $subscription);
                }
            }
            DB::commit();

            if ($canUseTrial) { # indicates a new customer
                $aweberService->setMailingList($subscription->stripe_plan)
                    ->subscribeCustomer($user);

                if (@$this->request->product_purchase === 'on') {
                    $aweberService->subscribeToOrderBumpList($user);
                }
            }

            if (@$this->request->product_purchase === 'on') {
                app(SubscriptionService::class)->oneTimePayment($user, $this->request->paymentMethod);
            }

            app(TapfiliateService::class)->removeTapfiliateCookie();

            /**
             * Add to session after the DB transaction is done
             */
            $accessToken = $user->createToken($user->email)->accessToken;
            $userDetails = $user->getDetailsByUser($user);

            $response = ['user' => $userDetails, 'access_token' => $accessToken];
            \Session::put('groupkit_auth', base64_encode(json_encode($response)));

            return redirect()->route('gkthanks');
        } catch (Exception $e) {
            DB::rollBack();
            $message = $e->getMessage();

            return view('plans.error', compact('message'));
        }
    }

    /**
     * This function is used to cancel or resume subscription (Auto - renew on & off)
     *
     * @see \Laravel\Cashier\Subscription::resume
     * @see \Laravel\Cashier\Subscription::cancel
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function autoRenewPlan()
    {
        try {
            $user = $this->currentUser;
            $currentPlan = $user->activePlanDetails();
            $subscriptions = app(Subscription::class)->retrieve($currentPlan->stripe_id);
            if ($subscriptions->cancel_at_period_end) {
                $subscriptions->cancel_at_period_end = false;
                $subscriptions->save();
                $data['planLabel'] = 'Deactivate monthly recurring payment';
                return response()->json(
                    [
                        'status' => 'success',
                        'message' => __('Auto-renewal of the subscription has been enabled successfully.'),
                        'data' => $data
                    ]
                );
            } else {
                $subscriptions->cancel_at_period_end = true;
                $subscriptions->save();
                $data['planLabel'] = 'Activate monthly recurring payment';
                return response()->json(
                    [
                        'status' => 'success',
                        'message' => __('Auto-renewal of the subscription has been canceled successfully.'),
                        'data' => $data
                    ]
                );
            }
        } catch (Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage(), 'data' => '']);
        }
    }

    /**
     * This action is used to cancel the subscription immediately (not waiting for the end of the billing period)
     *
     * @see \Laravel\Cashier\Subscription::cancelNow
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function cancelSubscription()
    {
        $canceled = false;

        try {
            $canceled = app(SubscriptionService::class)->cancel($this->currentUser);
        } catch (Exception $e) {
            Bugsnag::notifyException($e);
            logger()->error($e->getMessage());
        }

        return response(
            ['message' => $canceled ? __('Subscription Cancelled.') : __('Could Not Cancel Subscription.')],
            $canceled ? Response::HTTP_OK : Response::HTTP_BAD_REQUEST
        );
    }

    /**
     * Cancels current subscription and adds new subscription of group kit pro monthly.
     *
     * @return HttpResponse that acknowledges or refuses the success of the upgrade plan process.
     */
    public function upgradeToProPlan(): HttpResponse
    {
        $user = $this->currentUser;
        Stripe::setApiKey(User::getStripeSecret($user->stripeId()));

        $success = false;
        $trialDays = null;

        try {
            $subscription = app(User::class)->getSubscriptionDetails($user->stripe_id);

            # If customer is on trial, we add left trial days in new subscription
            if ($subscription && $subscription->stripe_status === User::STRIPE_ACTIVE_STATUSES['trialing']) {
                $trialEnd = Carbon::parse($subscription->trial_ends_at);
                $trialDays = $trialEnd->diffInDays(now());
            }

            # If customer has remaining subscription days,
            # we add the trial days for new subscription according to the remaining money from current subscription
            if ($subscription && $subscription->stripe_status === User::STRIPE_ACTIVE_STATUSES['active']) {
                $trialDays = app(SubscriptionService::class)->getTrialsForNewSubscription(
                    $subscription,
                    Plan::STRIPE_PLAN_IDS[$user->stripe_account]['PRO_MONTHLY']
                );
            }

            app(SubscriptionService::class)->cancel($user); #cancels current subscription

            #creating new subscription of group kit pro monthly.
            $success = app(SubscriptionService::class)->subscription(
                $user,
                Plan::STRIPE_PLAN_IDS[$user->stripe_account]['PRO_MONTHLY'],
                $trialDays
            );
        } catch (Exception $e) {
            Bugsnag::notifyException($e);
            logger()->error($e->getMessage());
        }

        if ($success) {
            return response(
                ['message' => __('Plan has been upgraded successfully')],
                Response::HTTP_OK,
            );
        }

        return response(
            ['message' => __('Server error')],
            Response::HTTP_INTERNAL_SERVER_ERROR,
        );
    }

    /**
     * Continues the subscription if is paused or
     * Removes scheduled pausing the subscription if is scheduled or
     * Schedules the subscription pausing at the end of the current subscription period
     *
     * @return HttpResponse return status and message according to the request type and request execution
     */
    public function pauseOrContinueSubscription(): HttpResponse
    {
        try {
            Stripe::setApiKey(User::getStripeSecret($this->currentUser->stripeId()));

            $subscription = User::getCustomerSubscription($this->currentUser->stripeId());

            if ($subscription->pause_collection) {
                # if the subscription is currently paused, we only continue the subscription

                $requestData = [
                    'pause_collection' => '',
                ];

                $message = __('Subscription continued successfully.');
            } elseif (
                $subscription->metadata->pausing_subscription_scheduled
                && $subscription->metadata->pause_subscription_id
            ) {
                # if subscription is scheduled for pausing, we remove job schedule
                DB::table('jobs')->where('id', $subscription->metadata->pause_subscription_id)->delete();

                $requestData = [
                    'metadata' => '',
                ];

                $message = __('Subscription canceling declined.');
            } else {
                # if the subscription is not paused, we're scheduling pause the subscription
                $insertedJobId = app(Dispatcher::class)->dispatch(
                    (
                        new PauseSubscription(
                            $this->currentUser,
                            Subscriptions::PAUSE_TYPES['SUSPEND_SERVICE'],
                            $subscription->id,
                            Subscriptions::RESUME_PAUSED_SUBSCRIPTION_IN
                        )
                    )->delay(Carbon::createFromTimestamp($subscription->current_period_end)->subHour())
                );

                $requestData = [
                    'metadata' => [
                        'pausing_subscription_scheduled' => true,
                        'pause_subscription_id' => $insertedJobId,
                    ],
                ];

                $message = __('Subscription paused successfully.');
            }

            $response = app(SubscriptionService::class)->update($subscription->id, $requestData);
        } catch (Exception $e) {
            return response(['message' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }

        return response(
            ['message' => $response ? $message : __('Could not proceed request.')],
            $response ? Response::HTTP_OK : Response::HTTP_BAD_REQUEST,
        );
    }

    /**
     * Schedules current pro plan subscription to expire at the end of subscription date
     * and also schedule new basic plan subscription to activate on existing plan expiring date
     * and remove all other groups except selected primary group
     *
     * @return HttpResponse that acknowledges or refuses the success of the upgrade plan process.
     */
    public function downgradeToBasicPlan(): HttpResponse
    {
        $user = $this->currentUser;
        $selectedActiveGroup = (int) $this->request->post('listOfActiveGroups');

        if (!$selectedActiveGroup) {
            $selectedActiveGroup = app(User::class)->getGroupIdWithMostRecentlyAddedMember($user->id);
        }

        DB::beginTransaction();
        try {
            # Deleting existing job and primary group selections for the same user, if found.
            $primaryGroups = PrimaryGroup::where('user_id', $user->id)->get();

            if ($primaryGroups) {
                DB::table('jobs')
                    ->whereIn('id', $primaryGroups->pluck('job_id'))
                    ->delete();
            }

            # Cancels the current plan at period's end
            $currentPlan = $user->activePlanDetails();

            $previousSubscription = app(Subscription::class)->retrieve($currentPlan->stripe_id);
            $previousSubscription->cancel_at_period_end = true;
            $previousSubscription->save();

            #Scheduling basic Subscription at  period end starts
            $basicPlanSubscription = app(SubscriptionSchedule::class)->create([
                'customer' => $user->stripe_id,
                'start_date' => $previousSubscription->current_period_end,
                'phases' => [[
                    'items' => [[
                        'plan' => Plan::STRIPE_PLAN_IDS[$user->stripe_account]['BASIC'],
                    ]],
                ]],
            ]);

            #If Scheduling of basic Subscription is not successful.
            if (!$basicPlanSubscription->id) {
                return response(
                    ['message' => __('Something went wrong.')],
                    Response::HTTP_INTERNAL_SERVER_ERROR
                );
            }

            #If found any active group id, removing other groups except active one via job.
            if ($selectedActiveGroup) {
                $lastInsertedJobId = app(Dispatcher::class)
                    ->dispatch(
                        (new RemoveUsersInactiveGroup($user->id, $selectedActiveGroup))
                            ->delay(Carbon::createFromTimestamp($previousSubscription->cancel_at))
                    );

                #Adding users id,group id,job id in primary_group table after job is created.
                $primaryGroup = new PrimaryGroup();
                $primaryGroup->user_id = $user->id;
                $primaryGroup->facebook_group_id = $selectedActiveGroup;
                $primaryGroup->job_id = $lastInsertedJobId;
                $primaryGroup->save();
            }

            DB::commit();

            return response(
                ['message' => __('Your Subscription will be downgraded once current plan expired.')],
                Response::HTTP_OK
            );
        } catch (Exception $e) {
            logger()->info($e->getMessage());
            Bugsnag::notifyException($e);

            DB::rollBack();

            return response(
                ['message' => $e->getMessage()],
                Response::HTTP_BAD_REQUEST
            );
        }
    }
}
