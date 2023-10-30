<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Traits\GroupkitControllerBehavior;
use App\Mail\StripeSyncMail;
use App\Plan;
use App\Services\TapfiliateService;
use App\Services\SubscriptionService;
use App\User;
use Bugsnag\BugsnagLaravel\Facades\Bugsnag;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Laravel\Cashier\Http\Controllers\WebhookController as CashierController;
use Stripe\Customer;
use Stripe\Exception\ApiErrorException;
use Stripe\Exception\SignatureVerificationException;
use Stripe\WebhookSignature;
use Symfony\Component\Finder\Exception\AccessDeniedException;
use Symfony\Component\HttpFoundation\Response;
use Stripe\Stripe;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use App\Exceptions\Stripe\StripeIDAlreadyExistsException;
use App\Exceptions\Stripe\StripeDataIsMissingException;

/**
 * Class WebhookController represents webhooks from Stripe to confirm requested actions
 * @package App\Http\Controllers
 */
class WebhookController extends CashierController
{
    use GroupkitControllerBehavior;

    /**
     * Occurs whenever a customer is signed up for a new plan.
     * More info @link https://stripe.com/docs/api/events/types#event_types-customer.subscription.created
     *
     * @string
     */
    public const CUSTOMER_SUBSCRIPTION_CREATED_EVENT = 'customer.subscription.created';

    /**
     * Occurs when a PaymentIntent has successfully completed payment.
     * More info @link https://stripe.com/docs/api/events/types#event_types-payment_intent.succeeded
     *
     * @string
     */
    public const PAYMENT_INTENT_SUCCEEDED_EVENT = 'payment_intent.succeeded';

    /**
     * Stripe account from who request comes in. It can be `default` or `new`
     *
     * @var string
     */
    private string $stripeAccount = 'default';

    /**
     * @var Customer represent Stripe customer from the current webhook
     */
    private Customer $customer;

    /**
     * Creates a new WebhookController instance.
     * Adds {@see \Laravel\Cashier\Http\Middleware\VerifyWebhookSignature} middleware for
     * Stripe request signature verification
     *
     * @return void
     *
     * @throws AccessDeniedException if hook is not from configured Stripe accounts
     */
    public function __construct()
    {
        if (config('services.stripe.default.webhook.secret')) {
            try {
                WebhookSignature::verifyHeader(
                    request()->getContent(),
                    request()->header('Stripe-Signature'),
                    config('services.stripe.default.webhook.secret'),
                    config('services.stripe.default.webhook.tolerance')
                );
            } catch (SignatureVerificationException $exception) {
                if (!config('services.stripe.new.webhook.secret')) {
                    throw new AccessDeniedHttpException($exception->getMessage(), $exception);
                }

                # check if request comes from new Stripe account
                try {
                    WebhookSignature::verifyHeader(
                        request()->getContent(),
                        request()->header('Stripe-Signature'),
                        config('services.stripe.new.webhook.secret'),
                        config('services.stripe.new.webhook.tolerance')
                    );

                    $this->stripeAccount = 'new';
                } catch (SignatureVerificationException $exception) {
                    throw new AccessDeniedHttpException($exception->getMessage(), $exception);
                }
            }
        }
    }

    /**
     * Retrieves request from the Stripe and update
     *
     * @param Request $request for stripe webhook event data
     *
     * @return response successful if Tapfiliate commission is created otherwise error response
     */
    public function index(Request $request): response
    {
        try {
            $payload = $request->all();
            $type = $payload['type'];

            switch ($type) {
                case static::PAYMENT_INTENT_SUCCEEDED_EVENT:
                    $customerId = @$payload['data']['object']['customer'];
                    $amountPaid = @$payload['data']['object']['amount_received'];
                    $user = User::where('stripe_id', $customerId)->first();

                    /** Create Tapfiliate Customer Conversion */
                    if ($user && $user->ref_code) {
                        TapfiliateService::createConversion($user, $amountPaid);
                    }

                    /* Check if the subscription is the lifetime type and if so, assign it
                     to the customer. */
                    if (SubscriptionService::isLifetimeSubscription($payload)) {
                        $this->addLifeTimeSubscription($customerId, $payload, $this->stripeAccount);
                    }
                    break;
                case static::CUSTOMER_SUBSCRIPTION_CREATED_EVENT:
                    $customerId = $payload['data']['object']['customer'] ?? null;
                    if (!$customerId) {
                        break;
                    }

                    $this->createOrUpdateUser($customerId);
                    break;
            }
        } catch (Exception $e) {
            Bugsnag::notifyException($e);

            return response('Something went wrong', Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return response('Success', Response::HTTP_OK);
    }

    /**
     * Adds life time subscription to the customer
     *
     * @param array $requestData that came into our application from Stripe
     * @param string $stripeAccount specifies which Stripe merchant account that the new user will belong to
     * @param string $customerId of the {@see User} in Stripe
     *
     * @throws StripeIDAlreadyExistsException user creation isn't possible because Stripe ID already exists
     * @throws ApiErrorException upon a problem connecting with Stripe
     */
    private function addLifeTimeSubscription(string $customerId, array $requestData, string $stripeAccount)
    {
        try {
            $user = $this->getAndUpdateUser($customerId, $stripeAccount);

            if (!$user && User::withTrashed()->where('stripe_id', $customerId)->count() > 0) {
                throw new StripeIDAlreadyExistsException();
            }

            if (!$user) { #send email to the newly created customer
                $user = User::create([
                    'name' => $this->customer->name
                        ?? $requestData['data']['object']['metadata']['name']
                        ?? $requestData['data']['object']['charges']['data'][0]['shipping']['name']
                        ?? $requestData['data']['object']['charges']['data'][0]['billing_details']['name'],
                    'email' => $this->customer->email
                        ?? $requestData['data']['object']['metadata']['email']
                        ?? $requestData['data']['object']['charges']['data'][0]['billing_details']['email'],
                    'stripe_id' => $customerId,
                    'stripe_account' => $stripeAccount,
                ]);

                Mail::to($user->email)->send(new StripeSyncMail($user));
            }

            Stripe::setApiKey(getStripeSecret($stripeAccount));

            // Create lifetime subscription for the customer
            app(SubscriptionService::class)->subscription(
                $user,
                Plan::STRIPE_PLAN_IDS[$stripeAccount]['FREE_PRO']
            );
        } catch (Exception $e) {
            Bugsnag::notifyException($e);
            throw $e;
        }
    }

    /**
     * Gets user from the database if customer id provided filtered by email from the customer's Stripe account
     * if exists, otherwise filtered by customer id from the Stripe.
     * Cancels customer's subscription in if customer is found in the database.
     * If the customer moved to another Stripe account
     * updates user in the database to use stripe account from who request comes and provided stripe_id
     *
     * @param string|null $customerId of the {@see User} from Stripe
     * @param string $stripeAccount specifies which Stripe merchant account the moved user will belong to
     *
     * @return User|null null if customer doesn't exists in the database or customerId is not provided,
     *                   otherwise {@see User} from the database
     *
     * @throws ApiErrorException upon a problem connecting with Stripe
     */
    private function getAndUpdateUser(?string $customerId, string $stripeAccount): ?User
    {
        if (!$customerId) {
            return null;
        }

        $this->setCustomer($customerId, $stripeAccount);

        $whereCondition = $this->customer->email ? 'email' : 'stripe_id';
        $whereValue = $this->customer->email ?? $customerId;
        $user = User::where($whereCondition, $whereValue)->first();

        if ($user) {
            // cancel the subscription in the current Stripe account
            $user->cancelExistingSubscriptions();

            // check to see if we are moving the customer to a different Stripe account
            if ($user->stripe_account !== $stripeAccount) {
                $user->stripe_id = $customerId;
                $user->stripe_account = $stripeAccount;
                $user->save();

                // since the customer has been moved to a different stripe account, we cancel the subscription
                // here, as well, in anticipation of replacing it with a lifetime subscription (free pro monthly)
                $user->cancelExistingSubscriptions();
            }
        }

        return $user;
    }

    /**
     * Sets Stripe customer internal property {@see \App\Http\Controllers\WebhookController::$customer}
     *
     * @param string $customerId from Stripe for getting customer
     * @param string $stripeAccount specifies which Stripe merchant account that the new user will belong to
     *
     * @throws ApiErrorException upon a problem connecting with Stripe
     */
    private function setCustomer(string $customerId, string $stripeAccount): void
    {
        Stripe::setApiKey(getStripeSecret($stripeAccount));
        $this->customer = app(Customer::class)->retrieve($customerId);
    }

    /**
     * Creates or resurrects User in the database on Stripe Webhook event
     * {@see \App\Http\Controllers\WebhookController::CUSTOMER_SUBSCRIPTION_CREATED_EVENT}
     *
     * @param string $customerId of the customer in the Stripe dashboard.
     *
     * @throws StripeDataIsMissingException user create or update isn't possible because Stripe data is missing
     * @throws ApiErrorException upon a problem connecting with Stripe
     */
    public function createOrUpdateUser(string $customerId): void
    {
        $sendEmail = false; #determines whether we should send the welcome email
        $this->setCustomer($customerId, $this->stripeAccount);

        $customerId = $this->customer->id;
        $customerEmail = $this->customer->email;

        if (!$customerId || !$customerEmail) {
            throw new StripeDataIsMissingException();
        }

        # Checks if a user with the provided Stripe ID (customerID) or email address already exists in the system.
        $user = User::where('stripe_id', $customerId)->orWhere('email', $customerEmail);
        if ($user->exists()) {
            return;
        }

        # check if there is deleted account in database with the same stripe id or email
        $user = User::withTrashed()->where('stripe_id', $customerId)->orWhere('email', $customerEmail)->first();

        if (!$user) {
            $user = new User();
            $user->name = $this->customer->name
                ?? $this->customer->metadata->name
                ?? $this->customer->metadata->first_name . ' ' . $this->customer->metadata->last_name
            ;
            $user->email = $customerEmail;
            $user->stripe_id = $customerId;
            $user->stripe_account = $this->stripeAccount;
            $sendEmail = true;
        }

        $user->deleted_at = null;

        $user->save();

        if ($sendEmail) {
            Mail::to($user->email)->send(new StripeSyncMail($user));
        }
    }
}
