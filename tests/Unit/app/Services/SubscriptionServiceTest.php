<?php

namespace Tests\Unit\app\Services;

use App\Services\SubscriptionService;
use App\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Stripe\Customer;
use Tests\TestCase;
use App\Plan;
use stdClass;
use Exception;
use Stripe\Subscription;

/**
 * Class SubscriptionServiceTest adds test coverage for {@see SubscriptionService}
 *
 * @package Tests\Unit\app\Services
 * @coversDefaultClass \App\Services\SubscriptionService
 */
class SubscriptionServiceTest extends TestCase
{
    use DatabaseTransactions;
    use DatabaseMigrations;

    /**
     * Id of the user in his Stripe account
     *
     * @var string
     */
    public const STRIPE_ID = 'cus_JWDKsUR0rv1Y1Q';

    /**
     * Id of the Stripe subscription plan
     *
     * @var string
     */
    public const PLAN_ID = 'plan_H81ycCkDlKy6Ng';

    /**
     * Trial days of the Stripe subscription plan
     *
     * @var int
     */
    public const TRIAL_DAYS = 14;

    /**
     * Setup test dependencies
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
    }

    /**
     * @test
     * that updateCustomer update the customer's email address on stripe account and returns void response.
     *
     * @covers ::updateCustomer
     */
    public function updateCustomer_withVaildUserDetails_returnsVoidResponse()
    {
        $requestData = [
            'email' => 'test@gamil.com',
            'metadata' => [
                'email' => 'test@gamil.com',
            ],
        ];
        $user = User::factory()->create(['stripe_id' => 'cus_JWDKsUR0rv1Y1Q', 'email' => $requestData['email']]);

        $stripeCustomerMock = $this->mock(Customer::class);
        $stripeCustomerMock->shouldReceive('update')->andReturnTrue();

        $response = app(SubscriptionService::class)->updateCustomer($user, $requestData);

        $this->assertNull($response);
    }

    /**
     * @test
     * that createCustomer updated or creates customer according to the user's provided stripe id
     *
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     *
     * @covers ::createCustomer
     *
     * @dataProvider createCustomer_withOrWithoutStripeIdProvider
     *
     * @param string|null $stripeId of the user
     * @param string $expectedMethod of the Stripe Customer API that will be stubbed
     */
    public function createCustomer_withOrWithoutStripeId_returnsUser(?string $stripeId, string $expectedMethod)
    {
        $userMock = new UserMock();
        $userMock->stripe_id = $stripeId;
        $userMock->email = 'test@gmail.com';
        $userMock->name = 'John Doe';

        $paymentMethod = 'stripe';

        $requestData = [
            'email' => 'test@gmail.com',
            'name' => 'John Doe',
            'metadata' => [
                'name' => 'John Doe',
                'email' => 'test@gmail.com',
                'first_name' => 'John',
                'last_name' => ' Doe',
            ],
            'invoice_settings' => [
                'default_payment_method' => 'stripe',
            ],
            'payment_method' => 'stripe',
        ];

        $stripeCustomerMock = $this->mock('alias:Stripe\Customer');

        $stripeCustomerMock->expects()->$expectedMethod($requestData)->andReturnSelf();
        $stripeCustomerMock->id = self::STRIPE_ID;

        $returnedUser = SubscriptionService::createCustomer($userMock, $paymentMethod);

        $this->assertTrue($userMock->wasSaveCalled);
        $this->assertEquals(self::STRIPE_ID, $returnedUser->stripe_id);
        $this->assertEquals($userMock, $returnedUser);
    }

    /**
     * Data provider for {@see createCustomer_withOrWithoutStripeId_returnsUser}
     *
     * @return array[] with the stripe id of users and expected method
     */
    public function createCustomer_withOrWithoutStripeIdProvider(): array
    {
        return [
            [
                'stripeId' => self::STRIPE_ID,
                'expectedMethod' => 'update',
            ],
            [
                'stripeId' => null,
                'expectedMethod' => 'create',
            ],
        ];
    }

    /**
     * @test
     * that createCustomer throws exception when customer is not created or updated.
     *
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     *
     * @covers ::createCustomer
     */
    public function createCustomer_withInvalidCustomer_throwsException()
    {
        $userMock = new UserMock();
        $userMock->email = 'test@gmail.com';
        $userMock->name = 'John Doe';
        $paymentMethod = 'stripe';

        $this->assertNull($userMock->stripe_id);

        $requestData = [
            'email' => 'test@gmail.com',
            'name' => 'John Doe',
            'metadata' => [
                'name' => 'John Doe',
                'email' => 'test@gmail.com',
                'first_name' => 'John',
                'last_name' => ' Doe',
            ],
            'invoice_settings' => [
                'default_payment_method' => $paymentMethod,
            ],
            'payment_method' => $paymentMethod,
        ];

        $this->expectException(Exception::class);

        $this->mock('alias:Stripe\Customer')->expects()->create($requestData)->andThrow(new Exception());

        SubscriptionService::createCustomer($userMock, $paymentMethod);

        $this->assertFalse($userMock->wasSaveCalled);
    }

    /**
     * Creates subscription object stub
     *
     * @return object with subscription details
     */
    public function subscriptionDetailsSetUp(): object
    {
        $subscription = new stdClass();
        $subscription->id = 'sub_K6pHT7tlxNgftW';
        $subscription->stripe_id = 'sub_K6pHT7tlxNgftW';
        $subscription->stripe_status = 'trialing';
        $subscription->stripe_plan = Plan::STRIPE_PLAN_IDS['default']['PRO_MONTHLY'];
        $subscription->name = 'GroupKit Pro';
        $subscription->cancel_at_period_end = true;
        $subscription->current_period_start = now()->format('Y-m-d H:i:s');
        $subscription->current_period_end = now()->addMonths(1)->format('Y-m-d H:i:s');
        $subscription->ends_at = null;
        $subscription->trial_ends_at = now()->addMonths(1)->format('Y-m-d H:i:s');
        $subscription->quantity = 1;

        return $subscription;
    }

    /**
     * @test
     * that create subscription with valid stripe subscription creates a new subscription
     *
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     *
     * @covers ::subscription
     *
     * @dataProvider subscription_withValidOrInvalidStripeSubscriptionProvider
     *
     * @param stdClass|null $stripeSubscriptionStub Stub of subscription returned from Stripe.
     * @param bool $subscriptionCreationStatus Stripe subscription was created or not.
     */
    public function subscription_withValidOrInvalidStripeSubscription_returnsBool(
        ?stdClass $stripeSubscriptionStub,
        bool $subscriptionCreationStatus
    ) {
        $user = User::factory()->create(['stripe_id' => self::STRIPE_ID]);

        $requestData = [
            'customer' => $user->stripe_id,
            'items' => [[
                'plan' => self::PLAN_ID,
            ]],
            'trial_period_days' => self::TRIAL_DAYS,
        ];

        $stripeSubscriptionMock = $this->mock('alias:Stripe\Subscription');
        $stripeSubscriptionMock->expects()->create($requestData)->andReturn($stripeSubscriptionStub);
        $stripeSubscriptionMock->stripe_plan = self::PLAN_ID;

        $response = SubscriptionService::subscription($user, self::PLAN_ID, self::TRIAL_DAYS);

        $this->assertEquals($subscriptionCreationStatus, $response);
    }

    /**
     * Data provider for {@see subscription_withValidOrInvalidStripeSubscription_returnsBool}
     *
     * @return array[] with the subscription stub and expected creation status
     */
    public function subscription_withValidOrInvalidStripeSubscriptionProvider(): array
    {
        return [
            [
                'stripeSubscriptionStub' => $this->subscriptionDetailsSetUp(),
                'subscriptionCreationStatus' => true,
            ],
            [
                'stripeSubscriptionStub' => null,
                'subscriptionCreationStatus' => false,
            ],
        ];
    }

    /**
     * @test
     * that cancel subscription returns false if there is no current subscription plan
     *
     * @covers ::cancel
     */
    public function cancel_withoutCurrentPlan_returnsFalse()
    {
        $user = User::factory()->create(['stripe_id' => self::STRIPE_ID]);

        $this->mock(User::class)->expects()->getSubscriptionDetails($user->stripe_id)->andReturnNull();

        $response = SubscriptionService::cancel($user);

        $this->assertFalse($response);
    }

    /**
     * @test
     * that cancel subscription returns true if subscription is successfully cancelled or false if not
     *
     * @covers ::cancel
     *
     * @dataProvider cancel_withStripeSubscriptionCancelOrFailProvider
     *
     * @param bool $cancellationStatus of the stripe subscription
     */
    public function cancel_withStripeSubscriptionCancelOrFail_returnsBool(bool $cancellationStatus)
    {
        $user = User::factory()->create(['stripe_id' => self::STRIPE_ID]);
        $currentPlan = $this->subscriptionDetailsSetUp();

        $userMock = $this->mock(User::class);
        $userMock->expects()->getSubscriptionDetails($user->stripe_id)->andReturn($currentPlan);
        $userMock->expects()->getAttribute('stripe_id')->andReturn($currentPlan->stripe_id);

        $stripeSubscriptionMock =  $this->mock(Subscription::class);
        $stripeSubscriptionMock->expects()->retrieve($userMock->stripe_id)->andReturnSelf();
        $stripeSubscriptionMock->expects()->cancel()->andReturn($cancellationStatus);

        $response = SubscriptionService::cancel($user);

        $this->assertEquals($cancellationStatus, $response);
    }

    /**
     * Data provider for {@see cancel_withStripeSubscriptionCancelOrFail_returnsBool}
     *
     * @return array[] with the status of a subscription cancellation
     */
    public function cancel_withStripeSubscriptionCancelOrFailProvider(): array
    {
        return [
            [
                'cancellationStatus' => true,
            ],
            [
                'cancellationStatus' => false,
            ],
        ];
    }

    /**
     * @test
     * that update subscription updates a subscription with the given params
     * depending on the status of stripe update request
     *
     * @covers ::update
     *
     * @dataProvider update_withStripeUpdateSuccessOrFailureProvider
     *
     * @param stdClass|null $updatedSubscription Stub of updated subscription returned from Stripe.
     * @param bool $updateStatus Subscription was updated or not.
     */
    public function update_withStripeUpdateSuccessOrFailure_returnsBool(
        ?stdClass $updatedSubscription,
        bool $updateStatus
    ) {
        $user = User::factory()->create(['stripe_id' => self::STRIPE_ID]);

        $subscription = $this->subscriptionDetailsSetUp();

        $requestData = [
            'customer' => $user->stripe_id,
            'items' => [[
                'plan' => Plan::STRIPE_PLAN_IDS['default']['BASIC'],
            ]],
            'trial_period_days' => self::TRIAL_DAYS,
        ];

        $this->mock(Subscription::class)
            ->expects()
            ->update($subscription->id, $requestData)
            ->andReturn($updatedSubscription);

        $response = SubscriptionService::update($subscription->id, $requestData);

        $this->assertEquals($updateStatus, $response);
    }

    /**
     * Data provider for {@see update_withStripeUpdateSuccessOrFailure_returnsBool}
     *
     * @return array[] with updated subscriptions and expected return status
     */
    public function update_withStripeUpdateSuccessOrFailureProvider(): array
    {
        $subscriptionStub = $this->subscriptionDetailsSetUp();
        $subscriptionStub->stripe_plan = Plan::STRIPE_PLAN_IDS['default']['BASIC']; //change plan to basic

        return [
            [
                'updatedSubscription' => $subscriptionStub,
                'updateStatus' => true,
            ],
            [
                'updatedSubscription' => null,
                'updateStatus' => false,
            ],
        ];
    }

    /**
     * @test
     * that pause subscription pauses a subscription with param resumeAt given or not
     *
     * @covers ::pauseSubscription
     *
     * @dataProvider pauseSubscription_withResumeAtParameterProvidedOrNotProvider
     *
     * @param int|null $resumeAt timestamp to automatically resume stripe subscription
     */
    public function pauseSubscription_withResumeAtParameterProvidedOrNot_pausesSubscription(?int $resumeAt)
    {
        $subscription = $this->subscriptionDetailsSetUp();

        $requestData = [
            'pause_collection' => [
                'behavior' => 'pause_type',
            ],
        ];
        if ($resumeAt) {
            $requestData['pause_collection']['resumes_at'] = $resumeAt;
        }

        $this->mock(Subscription::class)
            ->expects()
            ->update($subscription->id, $requestData)
            ->andReturn($subscription);

        app(SubscriptionService::class)->pauseSubscription(
            $subscription->id,
            $requestData['pause_collection']['behavior'],
            $resumeAt
        );
    }

    /**
     * Data provider for {@see pauseSubscription_withResumeAtParameterProvidedOrNot_pausesSubscription}
     *
     * @return array[] with the value of the parameter resume at
     */
    public function pauseSubscription_withResumeAtParameterProvidedOrNotProvider(): array
    {
        return [
            [
                'resumeAt' => null,
            ],
            [
                'resumeAt' => time() + (14 * 24 * 60 * 60), //14 days from now
            ],
        ];
    }
}

/**
 * Helper class that extends Eloquent User Model and extends its save() method
 */
class UserMock extends User
{
    /**
     * initialize save() was called as false
     *
     * @var boolean true if save method has been called, otherwise false.
     */
    public bool $wasSaveCalled = false;

    /**
     * Sets true value to the {@see \Tests\Unit\app\Services\UserMock::$wasSaveCalled} property,
     * and returns that value
     *
     * @return boolean if save() was called, otherwise false
     */
    public function save(array $options = array()): bool
    {
        return $this->wasSaveCalled = true;
    }
}
