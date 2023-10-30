<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Traits\GroupkitControllerBehavior;
use App\Plan;
use App\Services\SubscriptionService;
use App\EmailUpdateRequest;
use App\User;
use Bugsnag\BugsnagLaravel\Facades\Bugsnag;
use Carbon\Carbon;
use Exception;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Stripe\Customer;
use Stripe\Exception\ApiErrorException;
use Stripe\Price;
use Stripe\SetupIntent;
use Stripe\Stripe;
use App\FacebookGroups;

class HomeController extends Controller
{
    use GroupkitControllerBehavior;

    /**
     * Sets the middleware and validation rules for this controller
     */
    protected function init()
    {
        $this->middleware(['auth'])->except('wait', 'activateNewEmail');
        $this->middleware('validate.ajax.request')->only('update', 'sendNewEmailActivationLink');

        $this->ajaxValidatorRules['update'] = !$this->request->updateOnlyTimeZone ? [
            'first_name' => ['required', 'string', 'max:32', 'regex:/^[a-zA-Z\-\'\,]+$/u'],
            'last_name'  => ['required', 'string', 'max:32', 'regex:/^[a-zA-Z\s\-\'\,]+$/u'],
            'password'   => 'nullable|min:8|same:confirmed',
        ] : [];

        $this->ajaxValidatorRules['paymentMethod'] = [
            'paymentMethod' => 'required|string',
        ];

        $this->ajaxValidatorRules['sendNewEmailActivationLink'] = [
            'email' => 'required|string|email|max:100|unique:users,email',
        ];
    }

    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function index()
    {
        return view('home');
    }

    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function setting(): Renderable
    {
        $user = $this->currentUser;
        $name = $user->name;
        $firstName = substr($name, 0, strpos($name, ' '));
        $lastName = substr($name, strlen($firstName));
        $user->first_name = trim($firstName);
        $user->last_name = trim($lastName);
        $card = null;
        $plan = null;
        $price = null;
        $userPlanIsNotAvailable = true;
        $userHasBasicPlan = false;
        $userHasProPlan = false;

        # if the user's plan is Recurring then only he/she can use `Cancel subscription`
        # and `activate/deactivate monthly recurring payment` functionalities.
        $isRecurringPlan = true;

        if ($user->hasStripeId()) {
            $plan = $user->activePlanDetails();
            if ($plan) {
                $price = app(Price::class)->retrieve($plan->stripe_plan);
                $userHasBasicPlan = $user->hasBasicPlan();
                $userHasProPlan = $user->hasProPlan();
                $userPlanIsNotAvailable = !$userHasBasicPlan && !$userHasProPlan;
            }

            if ($price && !$price->unit_amount) {
                # Removes `Monthly` from the plan name and adds `Lifetime` on the end of plan name
                # for the plan without costs
                $plan->name = str_replace('Monthly', '', $plan->name) . ' ' . __('Lifetime');
                $isRecurringPlan = false;
            }

            $stripeCustomer = app(Customer::class)->retrieve($user->stripeId());

            try {
                $card = $user->getCard(
                    $stripeCustomer->default_source
                    ?? $stripeCustomer->invoice_settings->default_payment_method
                );
            } catch (Exception $exception) {
                # if there is no card in the stripe we return response without card
            }
        }

        return view(
            'setting',
            compact(
                'user',
                'card',
                'plan',
                'price',
                'userPlanIsNotAvailable',
                'userHasBasicPlan',
                'userHasProPlan',
                'isRecurringPlan',
            )
        );
    }

    /**
     * Shows the Groupkit Thanks page.
     *
     * @return Renderable view response
     */
    public function gkthanks()
    {
        return view('gkthanks');
    }

    /**
     * Show Wait page.
     *
     * @throws ApiErrorException if the {@see \Stripe\ApiRequestor::request} fails
     *
     * @return Renderable view response
     */
    public function wait()
    {
        $plan = Plan::getYearlyPlan();

        return view('wait', compact('plan'));
    }

    /**
     * Shows the Giveaway page.
     *
     * @return Renderable view response
     */
    public function giveaway()
    {
        return view('giveaway');
    }

    /**
     * Updates the user settings
     *
     * @return JsonResponse that acknowledges or refutes the success of the user settings update
     */
    public function update(): JsonResponse
    {
        $user = $this->currentUser;

        if (!$this->request->input('updateOnlyTimeZone', false)) {
            $user->name = trim($this->request->input('first_name')) . ' ' . trim($this->request->input('last_name'));

            if (trim($this->request->input('password')) !== '') {
                $user->password = trim(Hash::make($this->request->input('password')));
            }
        }

        $user->timezone = $this->request->input('timeZone');

        try {
            $user->update();
        } catch (Exception $exception) {
            Bugsnag::notifyException($exception);
            return response()->json([
                'status' => 'error',
                'message' => 'Unable To Updated Successfully.',
                'data' => [],
            ]);
        }
        return response()->json([
            'status' => 'success',
            'message' => 'User Details Updated Successfully.',
            'data' => [],
        ]);
    }

    /**
     * Updates customer's default payment method
     *
     * @return RedirectResponse to the setting route with
     *                          success message if the customer's default payment method is updated
     *                          otherwise error message
     */
    public function updateCard(): RedirectResponse
    {
        $requestData = [
            'invoice_settings' => [
                'default_payment_method' => $this->request->paymentMethod,
            ],
        ];

        try {
            app(SubscriptionService::class)->updateCustomer($this->currentUser, $requestData);
        } catch (Exception $e) {
            Bugsnag::notifyException($e);
            return redirect()->route('setting')->with(['error' => __('Something went wrong')]);
        }

        return redirect()->route('setting')->with(['success' => __('Successfully updated card')]);
    }

    /**
     * Gets client secret from the Stripe for update card
     *
     * @return Response with client secret for the card update
     *
     * @throws ApiErrorException if the {@see \Stripe\ApiRequestor::request} fails
     */
    public function getClientSecret(): Response
    {
        Stripe::setApiKey(User::getStripeSecret($this->currentUser->stripeId()));
        $setupIntent = app(SetupIntent::class)->create(['customer' => $this->currentUser->stripeId()]);

        return response(['clientSecret' => $setupIntent->client_secret]);
    }

    /**
     * Sends an activation link to the users new email address
     *
     * @return Response return success message when activation link sent to email, otherwise an error message
     */
    public function sendNewEmailActivationLink(): Response
    {
        $sendActivationURL = app(EmailUpdateRequest::class)->sendActivationLink(
            $this->request->user()->email,
            $this->request->email,
            $this->request->getClientIp(),
        );

        return response(
            ['message' => $sendActivationURL['message']],
            $sendActivationURL['success'] ? Response::HTTP_OK : Response::HTTP_BAD_REQUEST
        );
    }

    /**
     * Updates the user's email address with provided email address if the activation code is valid
     * Updates the email id in stripe if the user has a stripe_id,
     * otherwise it will just update email address at users table.
     *
     * @param string $activationCode contains encrypted string
     *
     * @return Renderable view response
     *
     * @throws Exception upon a problem connecting with Stripe
     */
    public function activateNewEmail(string $activationCode)
    {
        $emailUpdateRequest = app(EmailUpdateRequest::class)
            ->where('activation_code', $activationCode)
            ->where('expires_at', '>=', now())
            ->first();

        if (!$emailUpdateRequest) {
            return view('update-email-message', ['message' => __('This link has expired.')]);
        }

        /**
         * validating weather requested email address is already occupied or not
         */
        $currentUsersHavingRequestedEmail = User::where('email', $emailUpdateRequest->new_email)->count();

        if ($currentUsersHavingRequestedEmail) {
            return view(
                'update-email-message',
                ['message' => __('There is already an account using this email address.')]
            );
        }
        $user = User::where('email', $emailUpdateRequest->current_email)->first();

        if ($user) {
            $user->email = $emailUpdateRequest->new_email;
            DB::beginTransaction();
            try {
                $user->save();
                if ($user->stripe_id) {
                    $requestData = [
                        'email' => $user->email,
                        'metadata' => [
                            'email' => $user->email,
                        ],
                    ];
                    app(SubscriptionService::class)->updateCustomer($user, $requestData);
                }
                DB::commit();
            } catch (Exception $e) {
                Bugsnag::notifyException($e);
                DB::rollBack();
                logger()->error($e->getMessage());

                return view('update-email-message', [
                    'message' => __('Something went wrong')
                ]);
            }
            # expiring current request as email changed successfully
            $emailUpdateRequest->where('activation_code', $activationCode)->update(['expires_at' => Carbon::now()]);

            return view('update-email-message', [
                'message' => __('Your email address has been updated successfully.')
            ]);
        }

        return view('update-email-message', ['message' => __('User is not exists in system.')]);
    }

    /**
     * Shows the subscription options page.
     *
     * @return View subscription options
     */
    public function subscriptionOptions(): View
    {
        $user = $this->currentUser;
        # if the user's plan is Recurring then only he/she can use `Cancel subscription`
        # and activate/deactivate `monthly recurring payment` functionalities.
        $isSubscriptionPaused = false;
        $isSubscriptionPauseScheduled = false;
        $subscriptionEndDate = null;

        if ($user->hasStripeId()) {
            $subscription = User::getCustomerSubscription($user->stripeId());

            if ($subscription) {
                $price = app(Price::class)->retrieve($subscription->plan->id);
                if ($price && !$price->unit_amount) {
                    //showing 404 page for the user who has lifetime plan.
                    abort(404);
                }

                $isSubscriptionPaused = (bool)$subscription->pause_collection;
                $isSubscriptionPauseScheduled = (bool)$subscription->metadata->pausing_subscription_scheduled;
                $subscriptionEndDate = $subscription->current_period_end;
            }
        }

        $listOfActiveGroups = FacebookGroups::where('user_id', $this->currentUser->id)->get();

        return view('subscriptionOptions', compact(
            'user',
            'isSubscriptionPaused',
            'isSubscriptionPauseScheduled',
            'listOfActiveGroups',
            'subscriptionEndDate'
        ));
    }

    /**
     * Returns page for team members without assigned groups
     *
     * @return View no groups assigned
     */
    public function noGroupsAssigned(): View
    {
        return view('errors.no-groups-assigned');
    }
}
