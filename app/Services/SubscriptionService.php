<?php

namespace App\Services;

use App\Plan;
use App\User;
use Bugsnag\BugsnagLaravel\Facades\Bugsnag;
use Carbon\Carbon;
use Exception;
use Stripe\Customer;
use Stripe\Exception\ApiErrorException;
use Stripe\Order;
use Stripe\PaymentIntent;
use Stripe\PaymentMethod;
use Stripe\Stripe;
use Stripe\Subscription;

/**
 * Class SubscriptionService represents helper service for Stripe subscription
 * @package App\Services
 */
class SubscriptionService
{

    /**
     * The amount for the lifetime test product.
     *
     * @var int
     */
    public const TEST_LIFETIME_SUBSCRIPTION_PRICE = 9999999;

    /**
     * Payment for order product.
     *
     * @param User $user instance
     * @param PaymentMethod|string|null $paymentMethod represents Stripe token ID
     *
     * @return false|void if confirm order or false response if exception is thrown
     */
    public static function oneTimePayment(User $user, $paymentMethod)
    {
        try {
            $orderCreate = Order::create([
                'items' => [
                    [
                        'type' => 'sku',
                        'parent' => config('app.group_lunch_templates_id'),
                    ],
                ],
                'currency' => 'usd',
                'shipping' => [
                    'name' => $user->name,
                    'address' => [
                        'line1' => '',
                        'city' => '',
                        'state' => '',
                        'country' => '',
                        'postal_code' => ''
                    ],
                ],
                'email' => $user->email,
            ]);
            $orderData = PaymentIntent::create([
                'amount' => $orderCreate->amount,
                'currency' => 'usd',
                'payment_method_types' => ['card'],
                'customer' => $user->stripe_id,
            ]);
            $order = PaymentIntent::retrieve($orderData->id);
            $order->confirm(['payment_method' => $paymentMethod]);
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Create or update a Stripe's customer account.
     * 
     * If the data for a credit card are invalied,
     * or some other parameters are invalid,
     * the methods for creating/updating a customer
     * will throw an ApiErrorException error.
     *
     * @param User $user instance
     * @param PaymentMethod|string|null $paymentMethod represents Stripe token ID
     *
     * @return User
     *
     * @throws ApiErrorException if the {@see \Stripe\ApiRequestor::request} fails
     */
    public static function createCustomer(User $user, $paymentMethod = null)
    {
        $email = $user->email;
        $name = $user->name;
        $firstName = substr($name, 0, strpos($name, ' '));
        $lastName = substr($name, strlen($firstName));

        $requestData = [
            'email' => $email,
            'name' => $name,
            'metadata' => [
                'name' => $name,
                'email' => $email,
                'first_name' => $firstName,
                'last_name' => $lastName,
            ],
            'invoice_settings' => [
                'default_payment_method' => $paymentMethod,
            ],
            'payment_method' => $paymentMethod,
        ];

        if ($user->stripe_id) {
            $customer = Customer::update($requestData);
        } else {
            $customer = Customer::create($requestData);
        }
        $user->stripe_id = $customer->id;
        $user->save();

        return $user;
    }

    /**
     * Updates Customer's details in the Stripe
     *
     * @param User $user represents customer in Stripe which default payment method will be updated
     * @param array $requestData with new values for the customer
     *                            (name, email, default_payment_method)
     *
     * @throws ApiErrorException if the {@see \Stripe\ApiRequestor::request} fails
     */
    public function updateCustomer(User $user, array $requestData): void
    {
        Stripe::setApiKey(User::getStripeSecret($user->stripeId()));
        Customer::update($user->stripeId(), $requestData);
    }

    /**
     * create subscription for user
     *
     * @param User $user instance
     * @param string $planId stripe planID
     * @param int|null $trialDay subscription trialDay
     *
     * @return bool true if subscription is successfully created, otherwise false
     */
    public static function subscription(User $user, string $planId, int $trialDay = null): bool
    {
        try {
            $subscription = Subscription::create([
                'customer' => $user->stripe_id,
                'items' => [[
                    'plan' => $planId,
                ]],
                'trial_period_days' => $trialDay
            ]);

            return (bool) @$subscription->id;
        } catch (Exception $e) {
            Bugsnag::notifyException($e);
            return false;
        }
    }

    /**
     * Cancels the subscription for a user
     *
     * @param User $user to be canceled
     *
     * @return bool true if the subscription is canceled, otherwise false
     */
    public static function cancel(User $user): bool
    {
        $currentPlan = app(User::class)->getSubscriptionDetails($user->stripe_id);
        if (!$currentPlan) {
            return false;
        }

        try {
            $subscription = app(Subscription::class)->retrieve($currentPlan->stripe_id)->cancel();
        } catch (Exception $e) {
            return false;
        }

        return (bool)$subscription;
    }

    /**
     * stripe will automatically attempt to collect subscription payment,
     *             stop & resume of the subscription current period.
     *
     * @param User $user instance for user
     *
     * @return bool if subscription details updated success then return true otherwise return false
     */
    public static function recurringPayment(User $user)
    {
        $currentPlan = User::getSubscriptionDetails($user->stripe_id);
        if (!$currentPlan) {
            return false;
        }

        try {
            $subscription = Subscription::retrieve($currentPlan->stripe_id);
            $subscription->cancel_at_period_end = !$user->status;
            $subscription->save();
        } catch (Exception $e) {
            return false;
        }

        return true;
    }

    /**
     * get all customers subscription details
     *
     * @param int $limit of objects to be returned
     * @param ?string $startingAfter parameter in order to fetch the next page of the list
     *
     * @return array for subscriptions list
     */
    public static function subscriptionList(int $limit, string $startingAfter = null): array
    {
        try {
            Stripe::setApiKey(config('services.stripe.default.secret'));
            $params = ['limit' => $limit];

            if ($startingAfter) {
                $params['starting_after'] = $startingAfter;
            }

            return [
                'message' => 'List of subscriptions.',
                'subscriptions' => Subscription::all($params),
                'success' => true,
            ];
        } catch (Exception $e) {
            return [
                'message' => $e->getMessage(),
                'success' => false,
            ];
        }
    }

    /**
     * Updates the subscription with the provided params
     *
     * @param string $subscriptionId contains users subscription id.
     * @param array $requestData contains key value params that will update the subscription.
     *
     * @return bool true if the subscription is updated, otherwise false.
     */
    public static function update(string $subscriptionId, array $requestData): bool
    {
        try {
            $subscription = app(Subscription::class)->update($subscriptionId, $requestData);

            return $subscription->id !== '';
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Returns trial days for upgrading to plan with provided $planId
     * if there is remaining money in current subscription, otherwise null
     *
     * @param object $currentSubscription including stripe plan id, subscription start and end period date
     * @param string $planId of the desired plan that want to upgrade to
     *
     * @return int|null as trial period for new subscription if there is remaining money in current subscription,
     *                  otherwise null
     *
     * @throws ApiErrorException if the {@see \Stripe\ApiRequestor::request} fails
     */
    public function getTrialsForNewSubscription(object $currentSubscription, string $planId): ?int
    {
        $currentPlan = Plan::getPlan($currentSubscription->stripe_plan);

        $subscriptionDays = Carbon::parse($currentSubscription->current_period_start)
            ->diffInDays(Carbon::parse($currentSubscription->current_period_end));

        $pricePerDay = round($currentPlan->amount / 100 / $subscriptionDays, 2, PHP_ROUND_HALF_DOWN);

        $usedDays = Carbon::parse($currentSubscription->current_period_start)->diffInDays(now());
        $remainingMoney = round(
            ($currentPlan->amount / 100) - ($usedDays * $pricePerDay),
            2,
            PHP_ROUND_HALF_DOWN
        );

        if ($remainingMoney > 0) {
            $planToUpgrade = Plan::getPlan($planId);
            $planToUpgradePricePerDay = round(
                $planToUpgrade->amount / 100 / $subscriptionDays,
                2,
                PHP_ROUND_HALF_DOWN
            );

            return (int)round($remainingMoney / $planToUpgradePricePerDay, 0, PHP_ROUND_HALF_DOWN);
        }

        return null;
    }

    /**
     * Pauses Stripe subscription and unpauses automatically if $resumeAt is provided
     *
     * @param string $subscriptionId of the subscription that will be paused
     * @param string $pauseType it can be one of the {@see Subscriptions::PAUSE_TYPES}
     * @param int|null $resumeAt subscription automatically in provided timestamp period
     *
     * @throws ApiErrorException if the request fails
     */
    public function pauseSubscription(string $subscriptionId, string $pauseType, int $resumeAt = null): void
    {
        $requestData = [
            'pause_collection' => [
                'behavior' => $pauseType,
            ],
        ];

        if ($resumeAt) { # resumes automatically after given timestamp
            $requestData['pause_collection']['resumes_at'] = $resumeAt;
        }

        app(Subscription::class)->update($subscriptionId, $requestData);
    }

    /**
     * Determines if the product for lifetime subscription has been purchased according
     * to the current environment.
     *
     * @param array $payload contains the data from Stripe's request.
     *
     * @return bool true if the request contains product for lifetime subscription, otherwise false.
     */
    public static function isLifetimeSubscription(array $payload): bool
    {
        if (app()->environment('production')) {
            $productDescription = $payload['data']['object']['metadata']['products'] ?? '';

            return preg_match('/\blifetime\b/', strtolower($productDescription));
        } else {
            return $payload['data']['object']['amount'] == self::TEST_LIFETIME_SUBSCRIPTION_PRICE;
        }
    }
}
