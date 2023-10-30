<?php

namespace Tests\Unit\app\Http\Controllers;

use App\Plan;
use App\Services\AweberService;
use App\Services\SubscriptionService;
use App\Services\TapfiliateService;
use App\User;
use Carbon\Carbon;
use Exception;
use Faker\Factory;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use stdClass;
use Stripe\Subscription;
use Stripe\Customer;
use Stripe\Product;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;
use App\FacebookGroups;

/**
 * Class SubscriptionControllerTest adds test coverage for {@see SubscriptionController}
 *
 * @package Tests\Unit\app\Http\Controllers
 * @coversDefaultClass \App\Http\Controllers\SubscriptionController
 */
class SubscriptionControllerTest extends TestCase
{
    use DatabaseMigrations;
    use DatabaseTransactions;

    /**
     * Setup test dependencies
     *
     * @return void
     */
    public function setUp(): void
    {
        parent::setUp();
        $this->assertGuest();
    }

    /**
     * @test
     * that redirectToUpgradePlan redirects to wait page
     * with purchase according to the purchase from the request
     *
     * @covers ::redirectToUpgradePlan
     *
     * @dataProvider redirectToUpgradePlan_withVariousProductPurchases
     *
     * @param string|null $requestPurchase that will be sent to the method API call
     * @param string $expectedPurchase in session of the tested method call
     */
    public function redirectToUpgradePlan_withVariousProductPurchases_redirectToWaitPage(
        ?string $requestPurchase,
        string $expectedPurchase
    ) {
        $faker = Factory::create();

        $requestData = [
            'paymentMethod' => 'pm_' . Str::random(10),
            'purchase' => $requestPurchase,
            'first_name' => $faker->firstName,
            'last_name' => $faker->lastName,
            'email' => $faker->email,
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'userData' => 'yes',
            'plan' => Plan::STRIPE_PLAN_IDS['default']['BASIC'],
        ];

        $response = $this->post(route('subscription.upgradePlan'), $requestData);

        $sessionData = [
            'paymentMethod' => $requestData['paymentMethod'],
            'purchase' => $expectedPurchase,
            'requestUser' => [
                'firstName' => $requestData['first_name'],
                'lastName' => $requestData['last_name'],
                'email' => $requestData['email'],
                'password' => $requestData['password'],
                'access_token' => null,
                'access_provider' => null,
                'userData' => $requestData['userData'],
            ],
        ];

        $response->assertSessionHas('token');
        $response->assertSessionHas('planId');

        $response->assertStatus(Response::HTTP_FOUND);
        $response->assertRedirect('wait');

        $this->assertEquals($sessionData, json_decode(base64_decode(session('token')), true));
        $this->assertEquals(Plan::STRIPE_PLAN_IDS['default']['BASIC'], session('planId'));
    }

    /**
     * Data provider for {@see redirectToUpgradePlan_withVariousProductPurchases_redirectToWaitPage}
     *
     * @return array[] containing request purchase and expected purchase of the tested method
     */
    public function redirectToUpgradePlan_withVariousProductPurchases(): array
    {
        return [
            ['requestPurchase' => 'yes', 'expectedPurchase' => 'on'],
            ['requestPurchase' => null, 'expectedPurchase' => 'off'],
        ];
    }

    /**
     * @test
     * that redirectToUpgradePlan returns validation message if the requested email is not valid
     *
     * @covers ::redirectToUpgradePlan
     *
     * @dataProvider redirectToUpgradePlan_withVariousInvalidEmailsProvider
     *
     * @param string $requestedEmail represents provided email for the request
     * @param array $inputEmails for import into database
     * @param array $expectedError containing key as validation field and value as validation message
     *                             of the tested method call
     */
    public function redirectToUpgradePlan_withVariousInvalidEmails_returnsVariousValidationMessage(
        string $requestedEmail,
        array $inputEmails,
        array $expectedError
    ) {
        $this->actingAsUser();
        foreach ($inputEmails as $inputEmail) {
            User::factory()->create(['email' => $inputEmail]);
        }

        $response = $this->post(
            route('subscription.upgradePlan',
            [
                'first_name' => 'John',
                'last_name' => 'Doe',
                'password' => 'password123',
                'password_confirmation' => 'password123',
                'email' => $requestedEmail,
            ]
        ));

        $response->assertStatus(Response::HTTP_FOUND);
        $response->assertSessionHasErrors($expectedError);
    }

    /**
     * Data provider for {@see redirectToUpgradePlan_withVariousInvalidEmails_returnsVariousValidationMessage}
     *
     * @return array[] containing requested email, input emails and expected result of the tested method
     */
    public function redirectToUpgradePlan_withVariousInvalidEmailsProvider(): array
    {
        return [
            'Email Is Required' => [
                'requestedEmail' => '',
                'inputEmails' => [],
                'expectedError' => [
                    'email' => 'The email field is required.',
                ],
            ],
            'Email String Is Too Long' => [
                'requestedEmail' => Str::random(99) . '@gmail.com',
                'inputEmails' => [],
                'expectedError' => [
                    'email' => 'The email may not be greater than 100 characters.',
                ],
            ],
            'Email Is Not Valid' => [
                'requestedEmail' => 'jane.doe-gmailcom',
                'inputEmails' => [],
                'expectedError' => [
                    'email' => 'The email must be a valid email address.',
                ],
            ],
            'Email Contains Illegal Characters' => [
                'requestedEmail' => "\"(),:;<>@[\]@'-/.`{",
                'inputEmails' => [],
                'expectedError' => [
                    'email' => 'The email must be a valid email address.',
                ],
            ],
            'Email Is Taken' => [
                'requestedEmail' => 'jane.doe@gmail.com',
                'inputEmails' => [
                    'jane.doe@gmail.com',
                    'johny.doe@gmail.com',
                ],
                'expectedError' => [
                    'email' => 'The email has already been taken.',
                ],
            ],
        ];
    }

    /**
     * @test
     * that redirectToUpgradePlan redirects with planId and token in session
     * if the requested email is valid and available
     *
     * @covers ::redirectToUpgradePlan
     *
     * @dataProvider redirectToUpgradePlan_withVariousValidAndAvailableEmailsProvider
     *
     * @param string $requestedEmail represents provided email for the request
     */
    public function redirectToUpgradePlan_withVariousValidAndAvailableEmails_setsPlanIdAndTokenInSession(
        string $requestedEmail
    ) {
        $this->actingAsUser();

        $inputEmails = [
            'sam.doe@gmail.com',
            'johny.smith@outlook.com',
        ];
        foreach ($inputEmails as $inputEmail) {
            User::factory()->create(['email' => $inputEmail]);
        }

        $response = $this->post(route(
                'subscription.upgradePlan',
                [
                    'first_name' => 'John',
                    'last_name' => 'Doe',
                    'password' => 'password123',
                    'password_confirmation' => 'password123',
                    'email' => $requestedEmail,
                    'plan' => Plan::STRIPE_PLAN_IDS['default']['PRO_MONTHLY']
                ]
            )
        );

        $response->assertStatus(Response::HTTP_FOUND);
        $response->assertSessionHasAll(['planId', 'token']);
    }

    /**
     * Data provider for {@see redirectToUpgradePlan_withVariousValidAndAvailableEmails_setsPlanIdAndTokenInSession}
     *
     * @return array[] containing requested email
     */
    public function redirectToUpgradePlan_withVariousValidAndAvailableEmailsProvider(): array
    {
        return [
            'Email Address With Uppercase And Lowercase Latin Letters' => [
                'requestedEmail' => 'UPPERCASELowercase@LatinLETTERS.coM',
            ],
            'Email Address With Digits' => [
                'requestedEmail' => '7403261958@7403.261958',
            ],
            'Email Address With Special Characters' => [
                'requestedEmail' => "!#$%&'*+-/=?^_`{|}~@!#$%&.*+=?^_|}~",
            ],
            'Email Address With Spaces And Illegal Special Characters' => [
                'requestedEmail' => '"() ,:;\"<>@[\]"@some.domain',
            ],
            'Email Address With 2nd level TLDs' => [
                'requestedEmail' => 'australian.address@example.com.au',
            ],
            'Simple Email Address' => [
                'requestedEmail' => 'simple@example.com',
            ],
            'Very Common Email Address' => [
                'requestedEmail' => 'very.common@example.com',
            ],
            'Email Address With + Symbol' => [
                'requestedEmail' => 'disposable.style.email.with+symbol@example.com',
            ],
            'Email Address With Hyphen' => [
                'requestedEmail' => 'other.email-with-hyphen@example.com',
            ],
            'Email Address With Fully Qualified Domain' => [
                'requestedEmail' => 'fully-qualified-domain@example.com',
            ],
            'Email Address With One-letter Local-part' => [
                'requestedEmail' => 'x@example.com',
            ],
            'Email Address With A Specific Domain' => [
                'requestedEmail' => 'example-indeed@strange-example.com',
            ],
            'Email Address With Slashes' => [
                'requestedEmail' => 'test/test@test.com',
            ],
            'Email Address With Top-level Domains' => [
                'requestedEmail' => 'example@s.example',
            ],
            'Email Address With Space Between The Quotes' => [
                'requestedEmail' => '" "@example.org',
            ],
            'Email Address With A Quoted Double Dot' => [
                'requestedEmail' => '"john..doe"@example.org',
            ],
            'Email Address With Bangified Host Route' => [
                'requestedEmail' => 'mailhost!username@example.org',
            ],
            'Email Address With % Escaped Mail Route' => [
                'requestedEmail' => 'user%example.com@example.org',
            ],
            'Email Address With Non-alphanumeric Character ' => [
                'requestedEmail' => 'user-@example.org',
            ],
            'Email Address With Local Domain Name' => [
                'requestedEmail' => 'admin@mailserver1',
            ],
            'Email Address With IP Address Instead Of Domains' => [
                'requestedEmail' => 'postmaster@[123.123.123.123]',
            ],
            'Email Address With IPv6 Address' => [
                'requestedEmail' => 'postmaster@[IPv6:2001:0db8:85a3:0000:0000:8a2e:0370:7334]',
            ],
            'guaranteed.network Email Address' => [
                'requestedEmail' => 'leon@guaranteed.network',
            ],
            'guaranteed.software Email Address' => [
                'requestedEmail' => 'developers@guaranteed.software',
            ],
            'gmail.com Email Address' => [
                'requestedEmail' => 'john.vega@gmail.com',
            ],
            'yahoo.com Email Address' => [
                'requestedEmail' => 'barbara.sparks@yahoo.com',
            ],
            'outlook.com Email Address' => [
                'requestedEmail' => 'james.hayden@outlook.com',
            ],
        ];
    }

    /**
     * @test
     * that redirectToUpgradePlan does not require password if user sign up via Facebook
     *
     * @covers ::redirectToUpgradePlan
     */
    public function redirectToUpgradePlan_withAccessTokenInSession_proceedWithoutPassword()
    {
        session(['access_token' => Hash::make(Str::random())]);

        $response = $this->post(route(
            'subscription.upgradePlan',
            [
                'first_name' => 'John',
                'last_name' => 'Doe',
                'email' => 'john.doe@gmail.com',
                'plan' => Plan::STRIPE_PLAN_IDS['default']['PRO_MONTHLY'],
            ]
        ));

        $response->assertRedirect('wait');
        $response->assertSessionHas('planId');
        $response->assertSessionHas('token');
    }

    /**
     * @test
     * that redirectToUpgradePlan returns password validation message
     * on providing invalid password and password confirmation values
     *
     * @covers ::redirectToUpgradePlan
     *
     * @dataProvider redirectToUpgradePlan_withVariousInvalidPasswordsProvider
     *
     * @param string $password value in the request to the tested method
     * @param string $passwordConfirmation value in the request to the tested method
     * @param string $expectedError of the tested method call
     */
    public function redirectToUpgradePlan_withVariousInvalidPasswords_returnsValidationErrorMessage(
        string $password,
        string $passwordConfirmation,
        string $expectedError
    ) {
        $response = $this->post(route(
            'subscription.upgradePlan',
            [
                'first_name' => 'John',
                'last_name' => 'Doe',
                'password' => $password,
                'password_confirmation' => $passwordConfirmation,
                'email' => 'john.doe@gmail.com',
                'plan' => Plan::STRIPE_PLAN_IDS['default']['PRO_MONTHLY'],
            ]
        ));

        $response->assertSessionHas('errors');
        $response->assertSessionHasErrors([
            'password' => $expectedError,
        ]);
    }

    /**
     * Data provider for {@see redirectToUpgradePlan_withVariousInvalidPasswords_returnsValidationErrorMessage}
     *
     * @return array[] containing password, password confirmation and expected error of the test method call
     */
    public function redirectToUpgradePlan_withVariousInvalidPasswordsProvider(): array
    {
        return [
            'Password needs to match password confirmation' => [
                'password' => 'password123',
                'passwordConfirmation' => 'password1234',
                'expectedError' => 'The password confirmation does not match.',
            ],
            'Password minimum length is 8 characters' => [
                'password' => 'passwor',
                'passwordConfirmation' => 'passwor',
                'expectedError' => 'The password must be at least 8 characters.',
            ],
        ];
    }

    /**
     * Creates {@see \Stripe\Plan} object stub
     *
     * @return object with plan details
     */
    private function planDetailsObjectSetUp(): object
    {
        $plan['id'] = Plan::STRIPE_PLAN_IDS['default']['BASIC'];
        $plan['product']['metadata']['trialLength'] = 14;

        return json_decode(json_encode($plan));
    }

    /**
     * Creates subscription object stub
     *
     * @return object with subscription details
     */
    public function subscriptionDetailsSetUp(): object
    {
        $subscription = new stdClass();
        $subscription->stripe_id = 'sub_JxtdJbgMeG1AI7';
        $subscription->stripe_status = 'active';
        $subscription->stripe_plan = Plan::STRIPE_PLAN_IDS['default']['BASIC'];
        $subscription->name = 'GroupKit Basic';
        $subscription->cancel_at_period_end = true;
        $subscription->current_period_start = Carbon::now()->format('Y-m-d H:i:s');
        $subscription->current_period_end = Carbon::now()->format('Y-m-d H:i:s');
        $subscription->ends_at = null;
        $subscription->trial_ends_at = null;
        $subscription->quantity = 1;
        $subscription->object = 'subscription';
        $subscription->cancel_at = 1633084755;
        $subscription->canceled_at = 1630492982;
        $subscription->created = 1630492755;
        $subscription->customer = 'cus_JqmLNJHI2CzGHv';
        $subscription->collection_method = 'charge_automatically';
        $subscription->start_date = now()->format('Y-m-d H:i:s');
        $subscription->status = 'active';

        return $subscription;
    }

    /**
     * @test
     * that create redirects to gkThanks page when user passed the valid request data
     * create monthly & yearly subscription {GroupKit Pro, GroupKit Basic, GroupKit Pro Annual}
     *
     * 1. When user registers and selects the monthly subscription plan,
     *                                     then user is given 14 days trial period,
     * 2. When user registers and selects Yearly subscription plan, then user will not have any trial period provided,
     * 3. When user have registered via tapfiliate program, than the referer user should receive commission
     *
     * @covers ::create
     *
     * @dataProvider create_withVariousSubscriptionsProvider
     *
     * @param string $stripePlanId of the stripe {@see \Stripe\Plan}
     * @param string $productPurchase represents that customer has bought Group Launch Templates if the value is on
     * @param string $referenceCode of the Tapfiliate integration
     * @param bool $hasPreviousSubscription indicator that confirms if the customer will get a trial period
     */
    public function create_withVariousSubscriptions_redirectsTogkThanksPage(
        string $stripePlanId,
        string $productPurchase,
        string $referenceCode,
        bool $hasPreviousSubscription
    ) {
        $this->artisan('passport:install');

        $user = User::factory()->create(
            [
                'name' => 'John Doe',
                'email' => 'john.doe@gmail.com',
                'password' => 'password',
                'ref_code' => $referenceCode,
            ]
        );
        $this->actingAs($user);

        $this->get(route('plans.show', $stripePlanId));

        $userMock = $this->mock(User::class);
        $userMock->shouldReceive('createUser')->andReturn($user);
        $userMock->shouldReceive('getSubscription')->andReturn($this->subscriptionDetailsSetUp());
        $userMock->shouldReceive('hasSubscription')->andReturn($hasPreviousSubscription);
        $userMock->shouldReceive('createToken')->andReturn((object)['accessToken' => Str::random(50)]);
        $userMock->shouldReceive('getDetailsByUser')->andReturn($user);
        $userMock->shouldReceive('planDetails')->andReturn($this->planDetailsObjectSetUp());

        $user->stripe_id = 'cus_' . Str::random(10);
        $subscriptionServiceMock = $this->mock(SubscriptionService::class);
        $subscriptionServiceMock->shouldReceive('createCustomer')->andReturn($user);
        $subscriptionServiceMock->shouldReceive('subscription')->andReturn(true);
        $subscriptionServiceMock->shouldReceive('oneTimePayment')->andReturn(true);

        $aweberServiceMock = $this->createMock(AweberService::class);
        $aweberServiceMock->expects(static::once())
            ->method('setMailingList')
            ->with($this->planDetailsObjectSetUp()->id)
            ->willReturnSelf();
        $aweberServiceMock->expects(static::once())->method('subscribeCustomer');
        $aweberServiceMock->expects(static::any())->method('subscribeToOrderBumpList');
        $this->app->instance(AweberService::class, $aweberServiceMock);

        $tapfiliateServiceMock = $this->mock(TapfiliateService::class);
        $tapfiliateServiceMock->shouldReceive('createCustomer')->andReturn(true);
        $tapfiliateServiceMock->shouldReceive('removeTapfiliateCookie');

        $response = $this->post(route('subscription.create'), [
            'plan' => $stripePlanId,
            'product_purchase' => $productPurchase,
            'paymentMethod' => '',
        ]);

        $response->assertSessionHas('groupkit_auth');
        $response->assertSee('gkthanks');
        $response->assertStatus(Response::HTTP_FOUND);
        $response->assertRedirect('gkthanks');
    }

    /**
     * Data provider for {@see create_withVariousSubscriptions_redirectsTogkThanksPage}
     *
     * @return array[] containing
     * stripe plan id,
     * product purchase param,
     * reference code for tapfiliate service,
     * has previous subscription indicator for set trial
     */
    public function create_withVariousSubscriptionsProvider(): array
    {
        return [
            'Correct Subscription with Group Launch Templates and Referral code' => [
                'stripePlanId' => Plan::STRIPE_PLAN_IDS['default']['PRO_MONTHLY'],
                'productPurchase' => 'on',
                'referenceCode' => 'test',
                'hasPreviousSubscription' => true,
            ],
            'Correct Subscription Parameter with No Group Launch Templates' => [
                'stripePlanId' => Plan::STRIPE_PLAN_IDS['default']['PRO_MONTHLY'],
                'productPurchase' => 'off',
                'referenceCode' => '',
                'hasPreviousSubscription' => true,
            ],
            'Correct Subscription GroupKit Basic with No Trial' => [
                'stripePlanId' => Plan::STRIPE_PLAN_IDS['default']['BASIC'],
                'productPurchase' => 'on',
                'referenceCode' => '',
                'hasPreviousSubscription' => false,
            ],
            'Correct Subscription GroupKit Pro with No Trial' => [
                'stripePlanId' => Plan::STRIPE_PLAN_IDS['default']['PRO_MONTHLY'],
                'productPurchase' => 'off',
                'referenceCode' => '',
                'hasPreviousSubscription' => false,
            ],
            'Correct Subscription GroupKit Pro Annual with No Trial' => [
                'stripePlanId' => Plan::STRIPE_PLAN_IDS['default']['PRO_ANNUAL'],
                'productPurchase' => 'on',
                'referenceCode' => '',
                'hasPreviousSubscription' => false,
            ],
        ];
    }

    /**
     * @test
     * that create returns error view when an exception is thrown
     *
     * @covers ::create
     */
    public function create_whenAnExceptionIsThrown_returnsErrorView()
    {
        $this->get(route('plans.show', Str::random(10)));

        $userMock = $this->mock(User::class);
        $userMock->shouldReceive('createUser')->andThrow(new Exception());

        $response = $this->post(route('subscription.create'), [
            'plan' => '',
            'product_purchase' => 'on',
            'paymentMethod' => 'pm_' . Str::random(10),
        ]);

        $response->assertViewIs('plans.error');
        $response->assertViewHas('message');
        $response->assertOk();
    }

    /**
     * @test
     * that autoRenewPlan returns an error response if the user has no subscription
     *
     * @covers ::autoRenewPlan
     */
    public function autoRenewPlan_whenAnExceptionIsThrown_returnsErrorResponse()
    {
        $this->actingAsUser();

        $subscription = $this->subscriptionDetailsSetUp();

        $userMock = $this->mock(User::class);
        $userMock->shouldReceive('activePlanDetails')->andReturn($subscription);

        $response = $this->get('autorenewplan');

        $response->assertOk();
        $response->assertJsonStructure(['status', 'message', 'data']);
        $response->assertJsonFragment([
            'status' => 'error',
            'message' => "Trying to get property 'stripe_id' of non-object",
            'data' => '',
        ]);
    }

    /**
     * @test
     * that autoRenewPlan returns a message in the response according to the current subscription cancel state
     *
     * @covers ::autoRenewPlan
     *
     * @dataProvider autoRenewPlan_withVariousRecurringStatusesProvider
     *
     * @param bool $cancelSubscriptionAtPeriodEnd represent cancel status after subscription end in the Stripe
     * @param string $expectedMessage of the tested method call
     */
    public function autoRenewPlan_withVariousRecurringStatuses_returnsMessageInResponse(
        bool $cancelSubscriptionAtPeriodEnd,
        string $expectedMessage
    ) {
        $this->actingAsUser();

        $subscription = $this->subscriptionDetailsSetUp();

        $userMock = $this->mock(User::class);
        $userMock->shouldReceive('activePlanDetails')->andReturn($subscription);
        $this->actingAs($userMock);

        $subMock = $this->getMockBuilder(Subscription::class)->disableOriginalConstructor()->getMock();
        $subMock->method('__get')->with('cancel_at_period_end')->willReturn($cancelSubscriptionAtPeriodEnd);
        $subMock->expects(static::once())->method('save');
        $subscriptionMock = $this->partialMock(Subscription::class);
        $subscriptionMock->shouldReceive('retrieve')->with($subscription->stripe_id)->andReturn($subMock);
        $subscriptionMock->shouldReceive('save');

        $response = $this->get('autorenewplan');

        $response->assertJsonStructure(['status', 'message']);
        $response->assertJsonFragment([
            'status' => 'success',
            'message' => $expectedMessage,
        ]);
        $response->assertOk();
    }

    /**
     * Data provider for {@see autoRenewPlan_withVariousRecurringStatuses_returnsMessageInResponse}
     *
     * @return array[] containing indicator of state for cancel subscription at end
     * and expected message of the tested method call
     */
    public function autoRenewPlan_withVariousRecurringStatusesProvider()
    {
        return [
            [
                'cancelSubscriptionAtPeriodEnd' => false,
                'expectedMessage' => 'Auto-renewal of the subscription has been canceled successfully.',
            ],
            [
                'cancelSubscriptionAtPeriodEnd' => true,
                'expectedMessage' => 'Auto-renewal of the subscription has been enabled successfully.',
            ],
        ];
    }

    /**
     * @test
     * that cancelSubscription returns success response if user cancels subscription.
     *
     * @covers ::cancelSubscription
     *
     * @dataProvider cancelSubscription_withVariousCancelStatesProvider
     *
     * @param bool $cancel result of the {@see \App\Services\SubscriptionService::cancel} method
     * @param int expectedStatusCode of the tested method call
     * @param string $expectedMessage of the tested method call
     */
    public function cancelSubscription_withVariousCancelStates_returnsProperMessage(
        bool $cancel,
        int $expectedStatusCode,
        string $expectedMessage
    ) {
        $this->actingAsUser();

        $subscriptionMock = $this->mock(SubscriptionService::class);
        $subscriptionMock->shouldReceive('cancel')->andReturn($cancel);

        $response = $this->get('cancelSubscription');

        $response->assertJsonStructure(['message']);
        $response->assertJsonFragment([
            'message' => $expectedMessage,
        ]);
        $response->assertStatus($expectedStatusCode);
    }

    /**
     * Data provider for {@see cancelSubscription_withVariousCancelStates_returnsProperMessage}
     *
     * @return array[] containing
     * cancel result of the {@see \App\Services\SubscriptionService::cancel} method
     * expected status and message of the tested method call
     */
    public function cancelSubscription_withVariousCancelStatesProvider(): array
    {
        return [
            [
                'cancel' => true,
                'expectedStatusCode' => Response::HTTP_OK,
                'expectedMessage' => 'Subscription Cancelled.',
            ],
            [
                'cancel' => false,
                'expectedStatusCode' => Response::HTTP_BAD_REQUEST,
                'expectedMessage' => 'Could Not Cancel Subscription.',
            ],
        ];
    }

    /**
     * @test
     * that cancelSubscription returns error response if an exception is thrown
     *
     * @covers ::cancelSubscription
     */
    public function cancelSubscription_whenAnExceptionIsThrown_returnsErrorResponse()
    {
        $this->actingAsUser();

        $subscriptionMock = $this->mock(SubscriptionService::class);
        $exceptionMessage = 'Could Not Cancel Subscription.';
        $subscriptionMock->shouldReceive('cancel')->andThrow(new Exception($exceptionMessage));

        $response = $this->get('cancelSubscription');

        $response->assertJsonStructure(['message']);
        $response->assertJsonFragment([
            'message' => $exceptionMessage,
        ]);
        $response->assertStatus(Response::HTTP_BAD_REQUEST);
    }

    /**
     * Creates the stripe subscription object with plan details.
     *
     * @return object $subscription with subscription details
     */
    private function getSubscriptionDetailsStubSetUp(): object
    {
        $subscriptions = (object) [
            'subscriptions' => (object) [
                'data' => [
                    (object) [
                        'id' => 'sub_JxtdJbgMeG1AI7',
                        'status' => 'active',
                        'plan' => (object) [
                            'id' => Plan::STRIPE_PLAN_IDS['default']['BASIC'],
                            'product' => Plan::STRIPE_PLAN_IDS['default']['BASIC'],
                            'nickname' => 'GroupKit Basic',
                        ],
                        'cancel_at_period_end' => true,
                        'current_period_start' => Carbon::now()->format('Y-m-d H:i:s'),
                        'current_period_end' => Carbon::now()->format('Y-m-d H:i:s'),
                        'ends_at' => null,
                        'cancel_at' => Carbon::now()->addDays(1)->format('Y-m-d H:i:s'),
                        'trial_end' => null,
                        'quantity' => 1,
                    ]
                ]
            ]
        ];

        return $subscriptions;
    }

    /**
     * that createUserWithSubscriptionSetUp create user and applied basic subscription to that user
     * and returns user object
     *
     * @return object with subscription mock details
     */
    public function createUserWithSubscriptionSetUp(): object
    {
        $subscriptionStub = $this->getSubscriptionDetailsStubSetUp();
        $subscriptionStubData = $subscriptionStub->subscriptions->data[0];

        $user = User::factory()->create(['stripe_id' => $subscriptionStubData->id]);
        $this->actingAs($user);

        $this->mock(Customer::class)
            ->shouldReceive('retrieve')
            ->andReturn($subscriptionStub);

        $this->mock(Product::class)
            ->shouldReceive('retrieve')
            ->withArgs([$subscriptionStubData->plan->product])
            ->andReturn((object)['name' => $subscriptionStubData->plan->nickname]);

        return $user;
    }

    /**
     * @test
     * that downgradeToBasicPlan returns error response when an exception is thrown
     *
     * @covers ::downgradeToBasicPlan
     */
    public function downgradeToBasicPlan_whenAnExceptionIsThrown_returnsErrorResponse()
    {
        $user = User::factory()->create(['stripe_id' => 'cus_JjJ2Dn36V5VroA', 'status' => 1]);
        $facebookGroup = FacebookGroups::factory()
            ->create(
                [
                    'user_id' => $user->id,
                    'deleted_at' => null,
                ]
            );

        $subscription = $this->subscriptionDetailsSetUp();

        $userMock = $this->mock(User::class);

        $userMock->shouldReceive('activePlanDetails')->andReturn($subscription);
        $userMock->shouldReceive('getGroupIdWithMostRecentlyAddedMember')->andReturn($facebookGroup->id);
        $this->actingAs($userMock);

        $this->createUserWithSubscriptionSetUp();

        $subMock = $this->getMockBuilder(Subscription::class)->disableOriginalConstructor()->getMock();
        $subMock->expects(static::once())->method('save');

        $subscriptionMock = $this->partialMock(Subscription::class);
        $subscriptionMock->shouldReceive('retrieve')
            ->withArgs([$subscription->stripe_id])
            ->andReturn($subMock);

        $subscriptionMock->shouldReceive('save')->andThrow(new Exception());

        $response = $this->post(route('subscription.downgradeToBasicPlan'), ['listOfActiveGroups' => '']);

        $response->assertJsonStructure(['message']);
        $response->assertStatus(Response::HTTP_BAD_REQUEST);
    }
}
