<?php

namespace Tests\Unit\app;

use App\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use ReflectionException;
use Stripe\Collection;
use Stripe\Exception\ApiErrorException;
use Stripe\Stripe;
use Tests\TestCase;
use App\Plan;
use Stripe\Plan as StripePlan;
use Tests\TestHelper;

/**
 * Class PlanTest adds test coverage for {@see Plan}
 *
 * @package Tests\Unit\app
 * @coversDefaultClass \App\Plan
 */
class PlanTest extends TestCase
{
    use DatabaseTransactions;
    use DatabaseMigrations;

    /**
     * Sets property default value.
     *
     * @throws ReflectionException if stripeApiKeyWasSet or apiKey property doesn't exist
     */
    protected function tearDown(): void
    {
        parent::tearDown();

        TestHelper::setNonPublicProperty(Plan::class, 'stripeApiKeyWasSet', false);
        TestHelper::setNonPublicProperty(Stripe::class, 'apiKey', null);
    }

    /**
     * SetUp method for test cases that needs both stripe accounts
     *
     * @return string[][] including stripe account
     */
    private function getStripeAccountsSetUp(): array
    {
        return [
            'User with default Stripe account' => [
                'stripeAccount' => 'default',
            ],
            'User with new Stripe account' => [
                'stripeAccount' => 'new',
            ],
        ];
    }

    /**
     * @test
     * that __construct sets Stripe Api secret according to the user's stripe account
     *
     * @covers ::__construct
     *
     * @dataProvider __construct_withVariousUsersStripeAccountsProvider
     *
     * @param string $stripeAccount of the user that will be created in the test
     */
    public function __construct_withVariousUsersStripeAccounts_setsStripeApiSecret(string $stripeAccount)
    {
        $user = User::factory()->create(
            [
                'name' => 'John Doe',
                'email' => 'john.doe@gmail.com',
                'password' => 'password',
                'stripe_account' => $stripeAccount,
            ]
        );

        $this->actingAs($user);

        new Plan();

        $this->assertEquals($stripeAccount, $user->stripe_account);
        $this->assertEquals(config("services.stripe.$stripeAccount.secret"), Stripe::getApiKey());
    }

    /**
     * Data provider for {@see __construct_withVariousUsersStripeAccounts_setsStripeApiSecret}
     *
     * @return string[][] including stripe account for the user in test
     */
    public function __construct_withVariousUsersStripeAccountsProvider(): array
    {
        return $this->getStripeAccountsSetUp();
    }

    /**
     * SetUp method for test cases that uses expand property
     *
     * @return array including expand property
     */
    private function expandPropertySetUp(): array
    {
        return [
            'withoutProvidedExpandProperty' => [
                'expand' => null,
            ],
            'withExpandedProperty' => [
                'expand' => ['data.product'],
            ],
        ];
    }

    /**
     * @test
     * that getPlans returns plans collection from the Stripe
     *
     * @covers ::getPlans
     *
     * @dataProvider getPlans_withVariousProvidedStatesProvider
     *
     * @param ?array $expand property with additional data from the Stripe plans
     *
     * @throws ApiErrorException if the {@see \Stripe\ApiRequestor::request} fails
     */
    public function getPlans_withVariousProvidedStates_getsPlansFromStripe(?array $expand)
    {
        $this->mock(StripePlan::class)
            ->allows('all')
            ->once()
            ->with($expand ? [['expand' => $expand]] : '')
            ->andReturn($this->mock(Collection::class));

        $this->assertInstanceOf(Collection::class, Plan::getPlans($expand));
    }

    /**
     * Data provider for {@see getPlans_withVariousProvidedStates_getsPlansFromStripe}
     *
     * @return array containing expand property
     */
    public function getPlans_withVariousProvidedStatesProvider(): array
    {
        return $this->expandPropertySetUp();
    }

    /**
     * @test
     * that getPlan gets plan from Stripe with or without provided expand property
     *
     * @covers ::getPlan
     *
     * @dataProvider getPlan_withVariousExpandPropertyStatesProvider
     *
     * @param ?array $expand property with additional data from the Stripe plans
     *
     * @throws ApiErrorException if the {@see \Stripe\ApiRequestor::request} fails
     */
    public function getPlan_withVariousExpandPropertyStates_getsStripePlan(?array $expand)
    {
        $stripePlanId = Plan::STRIPE_PLAN_IDS['new']['BASIC'];

        $this->mock(StripePlan::class)
            ->allows('retrieve')
            ->once()
            ->with($expand ? ['id' => $stripePlanId, ['expand' => $expand]] : $stripePlanId)
            ->andReturnSelf();

        $result = Plan::getPlan($stripePlanId, $expand);

        $this->assertInstanceOf(StripePlan::class, $result);
    }

    /**
     * Data provider for {@see getPlan_withVariousExpandPropertyStates_getsStripePlan}
     *
     * @return array containing expand property
     */
    public function getPlan_withVariousExpandPropertyStatesProvider(): array
    {
        return $this->expandPropertySetUp();
    }

    /**
     * @test
     * that getYearlyPlan gets plan from the merchant (new or default) according to the user stripe account field
     *
     * @covers ::getYearlyPlan
     *
     * @dataProvider getYearlyPlan_withVariousUserStripeAccountsProvider
     */
    public function getYearlyPlan_withVariousUserStripeAccounts_getsPlanFromStripeMerchant(
        string $stripeAccount
    ) {
        $user = User::factory()->create(
            [
                'name' => 'John Doe',
                'email' => 'john.doe@gmail.com',
                'password' => 'password',
                'stripe_account' => $stripeAccount,
            ]
        );

        $this->actingAs($user);

        $this->mock(StripePlan::class)
            ->allows('retrieve')
            ->once()
            ->with(Plan::STRIPE_PLAN_IDS[$user->stripe_account]['PRO_ANNUAL'])
            ->andReturnSelf();

        $result = Plan::getYearlyPlan();

        $this->assertInstanceOf(StripePlan::class, $result);
    }

     /**
     * Data provider for {@see __construct_withVariousUsersStripeAccounts_setsStripeApiSecret}
     *
     * @return string[][] including stripe account for the user in test
     */
    public function getYearlyPlan_withVariousUserStripeAccountsProvider(): array
    {
        return $this->getStripeAccountsSetUp();
    }

    /**
     * @test
     * that getDisplayedPlans returns stripe product that has flag display on plans page
     * in the plan metadata or in the product metadata
     *
     * @covers ::getDisplayedPlans
     */
    public function getDisplayedPlans_always_returnsStripeProducts()
    {
        $plans = (object)[
            'data' => [
                (object)[
                    'name' => 'GroupKit Pro',
                    'metadata' => (object)['display_on_plans_page' => true],
                ],
                (object)[
                    'name' => 'GroupKit Basic',
                    'product' => (object)[
                        'metadata' => (object)[
                            'display_on_plans_page' => true,
                        ],
                    ],
                ],
                (object)[
                    'name' => 'GroupKit Pro Yearly',
                    'metadata' => (object)['display_on_plans_page' => false],
                ],
                (object)[
                    'name' => 'GroupKit Pro LifeTime',
                    'product' => (object)[
                        'metadata' => (object)[
                            'display_on_plans_page' => false,
                        ],
                    ],
                ],
            ],
        ];

        $this->mock(StripePlan::class)
            ->allows('all')
            ->once()
            ->with([['expand' => ['data.product']]])
            ->andReturn($plans);

        $result = Plan::getDisplayedPlans();

        $this->assertContains('GroupKit Pro', array_column($result, 'name'));
        $this->assertContains('GroupKit Basic', array_column($result, 'name'));
        $this->assertNotContains('GroupKit Pro Yearly', array_column($result, 'name'));
        $this->assertNotContains('GroupKit Pro LifeTime', array_column($result, 'name'));
    }

    /**
     * @test
     * that __callStatic trigger private static method from the {@see Plan} model class
     * and sets {@see \Stripe\Stripe::$apiKey}
     *
     * @covers \App\Plan::__callStatic
     *
     * @throws ApiErrorException if the {@see \Stripe\ApiRequestor::request} fails
     */
    public function __callStatic_withoutAlreadySetStripeApiKeyProperty_setsStripeApiKey()
    {
        $this->mock(StripePlan::class)
            ->allows('retrieve')
            ->once()
            ->with(Plan::STRIPE_PLAN_IDS['default']['PRO_ANNUAL'])
            ->andReturnSelf();

        Plan::getYearlyPlan();

        $this->assertEquals(config('services.stripe.default.secret'), Stripe::getApiKey());
    }

    /**
     * @test
     * that __callStatic does not set {@see \Stripe\Stripe::$apiKey}
     * when property {@see \App\Plan::$stripeApiKeyWasSet} is already true
     *
     * @covers \App\Plan::__callStatic
     *
     * @throws ReflectionException if stripeApiKeyWasSet property doesn't exist
     * @throws ApiErrorException if the {@see \Stripe\ApiRequestor::request} fails
     */
    public function __callStatic_withAlreadySetStripeApiKeyProperty_doesntSetStripeApiKey()
    {
        TestHelper::setNonPublicProperty(Plan::class, 'stripeApiKeyWasSet', true);

        $this->mock(StripePlan::class)
            ->allows('retrieve')
            ->once()
            ->with(Plan::STRIPE_PLAN_IDS['default']['PRO_ANNUAL'])
            ->andReturnSelf();

        Plan::getYearlyPlan();

        $this->assertNull(Stripe::getApiKey());
    }
}
