<?php

namespace Tests\Unit\app;

use App\FacebookGroups;
use App\GroupMembers;
use App\Mail\TeamMemberMail;
use App\Notifications\VerifyUserMail;
use App\OwnerToTeamMember;
use App\Services\SubscriptionService;
use App\TeamMemberGroupAccess;
use App\User;
use App\Plan;
use Carbon\Carbon;
use ErrorException;
use Exception;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use InvalidArgumentException;
use Laravel\Socialite\Facades\Socialite;
use Mockery;
use stdClass;
use Stripe\Customer;
use Stripe\Exception\ApiErrorException;
use Stripe\Product;
use Tests\TestCase;
use Tests\Unit\app\Http\Controllers\API\GroupControllerTest;

/**
 * Class UserTest adds test coverage for {@see User}
 *
 * @package Tests\Unit\app
 * @coversDefaultClass \App\User
 */
class UserTest extends TestCase
{
    use DatabaseTransactions;
    use DatabaseMigrations;

    /**
     * @test
     * that sendEmailVerificationNotification notify user with {@see VerifyUserMail}
     *
     * @covers ::sendEmailVerificationNotification
     */
    public function sendEmailVerificationNotification_withInvalidRequest_returnsVoidResponse()
    {
        $currentMock = $this->getMockBuilder(User::class)
            ->onlyMethods(['notify'])
            ->disableOriginalConstructor()
            ->getMock();

        $userName = 'Djuradj Kastriot';

        $currentMock->setAttribute('name', $userName);
        $currentMock->expects(static::once())->method('notify')->with(new VerifyUserMail($userName));

        $currentMock->sendEmailVerificationNotification();
    }

    /**
     * @test
     * that methods returns relationship between provided tables
     *
     * @covers ::teamMemberGroupAccess
     * @covers ::groupsOwned
     *
     * @dataProvider methods_withVariousMethodNamesProvider
     *
     * @param string $methodName of the tested method
     * @param string $expectedClass of the tested method result
     */
    public function methods_withVariousMethodNames_returnsGroupDetails(
        string $methodName,
        string $expectedClass
    ) {
        $owner = $this->actingAsUser();
        $facebookGroup = FacebookGroups::factory()->create(['user_id' => $owner->id]);
        TeamMemberGroupAccess::insert(
            [
                'user_id'           => $owner->id,
                'facebook_group_id' => $facebookGroup->id,
            ]
        );

        $result = $owner->$methodName();

        $this->assertInstanceOf($expectedClass, $result);

        $result = $result->first();
        $this->assertEquals($facebookGroup->id, $result->id);
        $this->assertEquals($facebookGroup->fb_id, $result->fb_id);
        $this->assertEquals($facebookGroup->fb_name, $result->fb_name);
        $this->assertEquals($owner->id, $result->user_id);
    }

    /**
     * Data provider for {@see methods_withVariousMethodNames_returnsGroupDetails}
     *
     * @return string[][] containing name of the tested method and expected instance of the tested method result
     */
    public function methods_withVariousMethodNamesProvider()
    {
        return [
            [
                'methodName' => 'teamMemberGroupAccess',
                'expectedClass' => BelongsToMany::class,
            ],
            [
                'methodName' => 'groupsOwned',
                'expectedClass' => HasMany::class,
            ],
        ];
    }

    /**
     * @test
     * that hasSubscription returns 1 when the user has an active subscription, otherwise returns 0
     *
     * @covers ::hasSubscription
     *
     * @dataProvider hasSubscription_withVariousSubscriptionProvider
     *
     * @param object|null $subscription with subscription details (id, status, plan)
     * @param int $expectedResult of the tested method call
     */
    public function hasSubscription_withVariousSubscription_returnIntegerResponse(
        ?object $subscription,
        int $expectedResult
    ) {
        $user = User::factory()->create(['stripe_id' => 'cus_312312das23']);

        $stripeCustomerMock = $this->mock(Customer::class);
        $stripeCustomerMock->shouldReceive('retrieve')->andReturn($subscription);

        $currentMock = $this->partialMock(User::class);

        $result = $currentMock->hasSubscription($user->stripe_id);

        $this->assertIsInt($result);
        $this->assertEquals($expectedResult, $result);
    }

    /**
     * Creates the stripe subscription object with plan details, periods of the subscription ...
     *
     * @return object $subscription with subscription details
     */
    private function getSubscriptionDetailsStubSetUp(): object
    {
        $subscription = [];
        $subscription['id'] = 'sub_123';
        $subscription['status'] = 'active';
        $subscription['plan']['id'] = Plan::STRIPE_PLAN_IDS['default']['BASIC'];
        $subscription['plan']['product'] = Plan::STRIPE_PLAN_IDS['default']['BASIC'];
        $subscription['plan']['nickname'] = 'GroupKit Basic';
        $subscription['cancel_at_period_end'] = true;
        $subscription['current_period_start'] = Carbon::parse(now())
            ->format('Y-m-d H:i:s');
        $subscription['current_period_end'] = Carbon::parse(now())
            ->format('Y-m-d H:i:s');
        $subscription['ends_at'] = null;
        $subscription['cancel_at'] = null;
        $subscription['trial_end'] = null;
        $subscription['quantity'] = 1;
        $subscriptions['subscriptions']['data'][0] = (object)$subscription;

        return json_decode(json_encode($subscriptions));
    }

    /**
     * Data provider for {@see hasSubscription_withVariousSubscription_returnIntegerResponse}
     *
     * @return array[] containing subscription of the stripe and expected result of the tested method call
     */
    public function hasSubscription_withVariousSubscriptionProvider(): array
    {
        return [
            'Active subscription' => [
                'subscription' => $this->getSubscriptionDetailsStubSetUp(),
                'expectedResult' => 1,
            ],
            'Inactive subscription' => [
                'subscription' => null,
                'expectedResult' => 0,
            ],
        ];
    }

    /**
     * @test
     * that activePlanDetails returns subscription response
     *
     * @covers ::activePlanDetails
     */
    public function activePlanDetails_always_returnSubscriptionResponse()
    {
        $user = User::factory()->create(['stripe_id' => 'cus_312312das23']);
        $this->actingAs($user);

        $stripeCustomerMock = $this->mock(Customer::class);
        $subscriptionStub = $this->getSubscriptionDetailsStubSetUp();
        $stripeCustomerMock->shouldReceive('retrieve')->andReturn($subscriptionStub);

        $userMock = $this->partialMock(User::class);
        $this->mock(Product::class)
            ->shouldReceive('retrieve')
            ->withArgs([$subscriptionStub->subscriptions->data[0]->plan->product])
            ->andReturn((object)['name' => $subscriptionStub->subscriptions->data[0]->plan->nickname]);

        $result = $userMock->activePlanDetails();

        $this->assertIsObject($result);

        $subscriptionStubData = $subscriptionStub->subscriptions->data[0];
        $this->assertEquals($subscriptionStubData->id, $result->stripe_id);
        $this->assertEquals($subscriptionStubData->status, $result->stripe_status);
        $this->assertEquals($subscriptionStubData->plan->id, $result->stripe_plan);
        $this->assertEquals($subscriptionStubData->plan->nickname, $result->name);
        $this->assertEquals($subscriptionStubData->current_period_start, $result->current_period_start);
        $this->assertEquals($subscriptionStubData->current_period_end, $result->current_period_end);
        $this->assertEquals($subscriptionStubData->cancel_at, $result->ends_at);
        $this->assertEquals($subscriptionStubData->trial_end, $result->trial_ends_at);
        $this->assertEquals($subscriptionStubData->quantity, $result->quantity);
    }

    /**
     * @test
     * that activePlan returns true when user subscription is activated, otherwise returns false
     *
     * @covers ::activePlan
     *
     * @dataProvider activePlan_withVariousSubscriptionRequestProvider
     *
     * @param array $subscription including subscription details (stripe_status, name, quantity)
     * @param bool $expectedResult of the tested method call
     */
    public function activePlan_withVariousSubscriptionRequest_returnsBoolValue(
        array $subscription,
        bool $expectedResult
    ) {
        $owner = User::factory()->create(['stripe_id' => 'cus_312312das23', 'status' => 1]);

        $subscription = (object)$subscription;

        $subscriptionStub = $this->getSubscriptionDetailsStubSetUp();
        $subscriptionStubData = $subscriptionStub->subscriptions->data[0];
        $this->mock(Product::class)
            ->shouldReceive('retrieve')
            ->withArgs([$subscriptionStubData->plan->product])
            ->andReturn((object)['name' => $subscriptionStubData->plan->nickname]);
        $subscriptionStubData->status = $subscription->stripe_status;
        $subscriptionStubData->plan->nickname = $subscription->name;
        $subscriptionStubData->cancel_at_period_end = $subscription->cancel_at_period_end;
        $subscriptionStubData->current_period_start = $subscription->current_period_start;
        $subscriptionStubData->current_period_end = $subscription->current_period_end;
        $subscriptionStubData->cancel_at = $subscription->cancel_at;
        $subscriptionStubData->trial_end = $subscription->trial_ends_at;

        $stripeCustomerMock = $this->mock(Customer::class);
        $stripeCustomerMock->shouldReceive('retrieve')->andReturn($subscriptionStub);

        $result = $owner->activePlan();

        $this->assertIsBool($result);
        $this->assertEquals($expectedResult, $result);
    }

    /**
     * Data provider for {@see activePlan_withVariousSubscriptionRequest_returnsBoolValue}
     *
     * @return array[] containing subscription details, expected result of the tested method call
     */
    public function activePlan_withVariousSubscriptionRequestProvider(): array
    {
        return [
            'GroupKit Basic Plan With Trial Expired'                => [
                'subscription'  => [
                    'stripe_status'        => 'trialing',
                    'name'                 => 'GroupKit Basic',
                    'cancel_at_period_end' => true,
                    'current_period_start' => Carbon::parse(now())->format('Y-m-d H:i:s'),
                    'current_period_end'   => '',
                    'cancel_at'            => null,
                    'trial_ends_at'        => Carbon::parse(now())->subDay()->format('Y-m-d H:i:s'),
                    'quantity'             => 1,
                ],
                'expectedResult' => false,
            ],
            'GroupKit Basic Plan With Trial Active'                 => [
                'subscription'  => [
                    'stripe_status'        => 'trialing',
                    'name'                 => 'GroupKit Basic',
                    'cancel_at_period_end' => true,
                    'current_period_start' => Carbon::parse(now())->format('Y-m-d H:i:s'),
                    'current_period_end'   => '',
                    'cancel_at'            => null,
                    'trial_ends_at'        => Carbon::parse(now())->addDay()->format('Y-m-d H:i:s'),
                    'quantity'             => 1,
                ],
                'expectedResult' => true,
            ],
            'GroupKit Basic Plan With Trial Current Period Active'  => [
                'subscription'  => [
                    'stripe_status'        => 'active',
                    'name'                 => 'GroupKit Basic',
                    'cancel_at_period_end' => true,
                    'current_period_start' => Carbon::parse(now())->format('Y-m-d H:i:s'),
                    'current_period_end'   => '',
                    'cancel_at'            => Carbon::parse(now())->addDay()->format('Y-m-d H:i:s'),
                    'trial_ends_at'        => null,
                    'quantity'             => 1,
                ],
                'expectedResult' => true,
            ],
            'GroupKit Basic Plan With Trial Current Period Expired' => [
                'subscription'  => [
                    'stripe_status'        => 'active',
                    'name'                 => 'GroupKit Basic',
                    'cancel_at_period_end' => true,
                    'current_period_start' => Carbon::parse(now())->format('Y-m-d H:i:s'),
                    'current_period_end'   => '',
                    'cancel_at'            => Carbon::parse(now())->subDay()->format('Y-m-d H:i:s'),
                    'trial_ends_at'        => null,
                    'quantity'             => 1,
                ],
                'expectedResult' => false,
            ],
            'GroupKit Basic Plan With Current Period Active'        => [
                'subscription'  => [
                    'stripe_status'        => 'active',
                    'name'                 => 'GroupKit Basic',
                    'cancel_at_period_end' => true,
                    'current_period_start' => Carbon::parse(now())->format('Y-m-d H:i:s'),
                    'current_period_end'   => Carbon::parse(now())->subDay()->format('Y-m-d H:i:s'),
                    'cancel_at'            => null,
                    'trial_ends_at'        => null,
                    'quantity'             => 1,
                ],
                'expectedResult' => false,
            ],
            'GroupKit Basic Plan With Current Period Expired'       => [
                'subscription'  => [
                    'stripe_status'        => 'active',
                    'name'                 => 'GroupKit Basic',
                    'cancel_at_period_end' => true,
                    'current_period_start' => Carbon::parse(now())->format('Y-m-d H:i:s'),
                    'current_period_end'   => Carbon::parse(now())->addDay()->format('Y-m-d H:i:s'),
                    'cancel_at'            => null,
                    'trial_ends_at'        => null,
                    'quantity'             => 1,
                ],
                'expectedResult' => true,
            ],
        ];
    }

    /**
     * @test
     * that activePlan returns false, when user doesn't have the subscription
     *
     * @covers ::activePlan
     */
    public function activePlan_whenUserDoesntHaveSubscription_returnsFalseValue()
    {
        $owner = User::factory()->create();

        $result = $owner->activePlan();

        $this->assertIsBool($result);
        $this->assertFalse($result);
    }

    /**
     * @test
     * that subscriptionsPlan returns plan name according to his subscription
     *
     * @covers ::subscriptionsPlan
     *
     * @dataProvider subscriptionsPlan_withVariousSubscriptionProvider
     *
     * @param string $planName of the Stripe
     */
    public function subscriptionsPlan_withVariousSubscription_returnsPlanName(string $planName)
    {
        $user = User::factory()->create(['stripe_id' => 'cus_312312das23']);

        $stripeCustomerMock = $this->mock(Customer::class);
        $subscriptionStub = $this->getSubscriptionDetailsStubSetUp();

        $this->mock(Product::class)
            ->shouldReceive('retrieve')
            ->withArgs([$subscriptionStub->subscriptions->data[0]->plan->product])
            ->andReturn((object)['name' => $planName]);
        $stripeCustomerMock->shouldReceive('retrieve')->andReturn($subscriptionStub);

        $result = User::subscriptionsPlan($user->id);

        $this->assertIsString($result);
        $this->assertEquals($planName, $result);
    }

    /**
     * Data provider for {@see subscriptionsPlan_withVariousSubscription_returnsPlanName}
     *
     * @return array[] containing stripe plan name
     */
    public function subscriptionsPlan_withVariousSubscriptionProvider(): array
    {
        return [
            'Subscribe With GroupKit Basic Plan' => [
                'planName' => 'GroupKit Basic',
            ],
            'Subscribe With GroupKit Pro' => [
                'planName' => 'GroupKit Pro',
            ],
            'Subscribe With GroupKit Pro Annual' => [
                'planName' => 'GroupKit Pro Annual',
            ],
        ];
    }

    /**
     * @test
     * that subscriptionsPlan returns N/A if user has not subscribed
     *
     * @covers ::subscriptionsPlan
     */
    public function subscriptionsPlan_withVariousSubscription_returnsNoneName()
    {
        $user = User::factory()->create();

        $stripeCustomerMock = $this->mock(Customer::class);
        $stripeCustomerMock->shouldReceive('retrieve')->andReturn(null);

        $result = User::subscriptionsPlan($user->id);

        $this->assertIsString($result);
        $this->assertEquals('N/A', $result);
    }

    /**
     * @test
     * that getTotalTeamMemberCount returns count of the team members that the owner has in his team
     *
     * @covers ::getTotalTeamMemberCount
     */
    public function getTotalTeamMemberCount_always_returnsCountOfTheTeamMembers()
    {
        $user = $this->actingAsUser();

        $teamMember = User::factory()->create();

        $ownerToTeamMembersData = [
            'team_member_id' => $teamMember->id,
            'owner_id'       => $user->id,
        ];
        OwnerToTeamMember::factory()->create($ownerToTeamMembersData);

        $result = $user->getTotalTeamMemberCount();

        $this->assertIsInt($result);
        $this->assertTrue($result > 0);
    }

    /**
     * @test
     * that getDetailsByUser returns the response of user details if is provided {@see User} instance
     *
     * @covers ::getDetailsByUser
     */
    public function getDetailsByUser_withProvidedUserInstance_returnsUserDetails()
    {
        $user = User::factory()->create(['stripe_id' => 'cus_312312das23']);

        $stripeCustomerMock = $this->mock(Customer::class);
        $subscriptionStub = $this->getSubscriptionDetailsStubSetUp();
        $this->mock(Product::class)
            ->shouldReceive('retrieve')
            ->withArgs([$subscriptionStub->subscriptions->data[0]->plan->product])
            ->andReturn((object)['name' => $subscriptionStub->subscriptions->data[0]->plan->nickname]);
        $stripeCustomerMock->shouldReceive('retrieve')->andReturn($subscriptionStub);

        $result = User::getDetailsByUser($user);

        $this->assertEquals($user->id, $result->id);
        $this->assertEquals($user->email, $result->email);
        $this->assertEquals($user->name, $result->name);
        $this->assertEquals($user->plan_name, $result->plan_name);
        $this->assertEquals($user->access_team, $result->access_team);
    }

    /**
     * @test
     * that getDetailsByUser throws an exception if provided value is not {@see User} instance
     *
     * @covers ::getDetailsByUser
     *
     * @dataProvider getDetailsByUser_withVariousDataTypesProvider
     *
     * @param int|string|array $inputValue for the tested method
     */
    public function getDetailsByUser_withVariousDataTypes_returnsUserDetails($inputValue)
    {
        $this->expectExceptionMessage("Trying to get property 'id' of non-object");
        $this->expectException(ErrorException::class);

        User::getDetailsByUser($inputValue);
    }

    /**
     * Data provider for {@see getDetailsByUser_withVariousDataTypes_returnsUserDetails}
     *
     * @return array containing input value for tested method
     */
    public function getDetailsByUser_withVariousDataTypesProvider()
    {
        return [
            'Input Value Is Int' => ['inputValue' => 4],
            'Input Value Is String' => ['inputValue' => 'message'],
            'Input Value Is Array' => ['inputValue' => []],
        ];
    }

    /**
     * @test
     * that canAddAnother returns bool value according to the basic plan limitation
     *
     * @covers ::canAddAnother
     *
     * @dataProvider canAddAnother_withValidCredentialsProvider
     *
     * @param string $itemType for check the limit of record added {member,group}
     * @param bool $expectedResult of the tested method call
     */
    public function canAddAnother_withVariousInputTypes_returnsBoolValue(
        string $itemType,
        bool $expectedResult
    ) {
        $user = User::factory()->create(['stripe_id' => 'cus_312312das23']);
        $this->actingAs($user);

        $facebookGroup = FacebookGroups::factory(['user_id' => $user->id])->create();

        GroupMembers::factory(10)->create(
            [
                'user_id'     => $user->id,
                'group_id'    => $facebookGroup->id,
                'is_approved' => 1,
            ]
        );

        $stripeCustomerMock = $this->mock(Customer::class);
        $subscriptionStub = $this->getSubscriptionDetailsStubSetUp();
        $this->mock(Product::class)
            ->shouldReceive('retrieve')
            ->withArgs([$subscriptionStub->subscriptions->data[0]->plan->product])
            ->andReturn((object)['name' => $subscriptionStub->subscriptions->data[0]->plan->nickname]);
        $stripeCustomerMock->shouldReceive('retrieve')->andReturn($subscriptionStub);

        $result = $user->canAddAnother($itemType);

        $this->assertIsBool($result);
        $this->assertEquals($expectedResult, $result);
    }

    /**
     * Data provider for {@see canAddAnother_withVariousInputTypes_returnsBoolValue}
     *
     * @return array[] containing item type for checking limitation, expected of the tested method call
     */
    public function canAddAnother_withValidCredentialsProvider(): array
    {
        return [
            'GroupKit Basic Plan With itemType As Group' => [
                'itemType' => 'group',
                'expectedResult' => true,
            ],
            'GroupKit Basic Plan With itemType As Member' => [
                'itemType' => 'member',
                'expectedResult' => true,
            ],
        ];
    }

    /**
     * @test
     * that canAddAnother returns true when stripe plan is not basic
     *
     * @covers ::canAddAnother
     */
    public function canAddAnother_withProSubscription_returnsTrue()
    {
        $user = User::factory()->create(['stripe_id' => 'cus_312312das23']);
        $this->actingAs($user);

        $facebookGroup = FacebookGroups::factory(['user_id' => $user->id])->create();

        GroupMembers::factory(10)->create(
            [
                'user_id'     => $user->id,
                'group_id'    => $facebookGroup->id,
                'is_approved' => 1,
            ]
        );

        $stripeCustomerMock = $this->mock(Customer::class);
        $subscriptionStub = $this->getSubscriptionDetailsStubSetUp();
        $subscriptionStub->subscriptions->data[0]->plan->id = 'pro_plan';
        $stripeCustomerMock->shouldReceive('retrieve')->andReturn($subscriptionStub);

        $result = $user->canAddAnother('member');

        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    /**
     * @test
     * that canAddAnother returns false if passed invalid current_period_start and current_period_end
     *
     * @covers ::canAddAnother
     */
    public function canAddAnother_withSubscriptionDateblank_returnsFalse()
    {
        $user = User::factory()->create(['stripe_id' => 'cus_312312das23']);
        $this->actingAs($user);

        $facebookGroup = FacebookGroups::factory(['user_id' => $user->id])->create();

        GroupMembers::factory(10)->create(
            [
                'user_id'     => $user->id,
                'group_id'    => $facebookGroup->id,
                'is_approved' => 1,
            ]
        );

        $subscription = new stdClass();
        $subscription->stripe_id = 'sub_123';
        $subscription->stripe_plan = Plan::STRIPE_PLAN_IDS['default']['BASIC'];
        $subscription->current_period_start = '';
        $subscription->current_period_end = '';

        $stripeCustomerMock = $this->mock(User::class);
        $stripeCustomerMock->shouldReceive('getSubscriptionDetails')->andReturn($subscription);

        $result = $user->canAddAnother('member');

        $this->assertIsBool($result);
        $this->assertFalse($result);
    }

    /**
     * @test
     * that createUser creates user without social data when the user is created without social login
     *
     * @covers ::createUser
     */
    public function createUser_withoutSocialLogin_returnsUserResponse()
    {
        $accessToken = '';
        $provider = '';
        $firstName = 'test';
        $lastName = 'test';
        $email = 'test@gmail.com';
        $password = '123456789';

        session()->put('access_token', $accessToken);
        session()->put('access_provider', $provider);

        $requestMock = $this->createMock(Request::class);
        $requestMock->expects(static::exactly(4))->method('__get')
            ->withConsecutive(['firstName'], ['lastName'], ['email'], ['password'])
            ->willReturnOnConsecutiveCalls($firstName, $lastName, $email, $password);
        $requestMock->expects(static::exactly(2))->method('session')->willReturnSelf();
        $requestMock->expects(static::exactly(2))
            ->method('get')
            ->withConsecutive(['access_token'], ['access_provider'])
            ->willReturnOnConsecutiveCalls($accessToken, $provider);

        $result = User::createUser($requestMock);

        $fullName = "$firstName $lastName";

        $this->assertEquals($result->name, $fullName);
        $this->assertEquals($result->email, $email);

        $this->assertDatabaseHas('users', [
            'name' =>  $fullName,
            'email' => $email,
            'ref_code' => null,
            'facebook_user_id' => null,
            'facebook_access_token' => null,
        ]);
    }

    /**
     * @test
     * that createUser creates customer with socialite data in the database
     *
     * @covers ::createUser
     */
    public function createUser_withSocialiteRequestData_returnsUserResponse()
    {
        $accessToken = 're3323fhsdnkjd1312';
        $provider = 'facebook';
        $firstName = 'test';
        $lastName = 'test';
        $email = 'test@gmail.com';
        $password = '123456789';

        session()->put('access_token', $accessToken);
        session()->put('access_provider', $provider);

        $requestMock = $this->createMock(Request::class);
        $requestMock->expects(static::exactly(3))
            ->method('__get')
            ->withConsecutive(['firstName'], ['lastName'], ['email'], ['password'])
            ->willReturnOnConsecutiveCalls($firstName, $lastName, $email, $password);
        $requestMock->expects(static::exactly(2))->method('session')->willReturnSelf();
        $requestMock->expects(static::exactly(2))
            ->method('get')
            ->withConsecutive(['access_token'], ['access_provider'])
            ->willReturnOnConsecutiveCalls($accessToken, $provider);

        $socialiteUserData = (object)[
            'id'       => 1234567890,
            'email'    => 'user@test.com',
            'nickName' => 'Pseudo',
            'name'     => 'Arlette Laguiller',
            'avatar'   => 'https://en.gravatar.com/userimage',
            'token'    => $accessToken,
        ];

        $providerMock = Mockery::mock('Laravel\Socialite\Contracts\Provider');
        $providerMock->shouldReceive('userFromToken')->andReturn($socialiteUserData);

        Socialite::shouldReceive('driver')->with('facebook')->andReturn($providerMock);

        $result = User::createUser($requestMock);

        $fullName = "$firstName $lastName";

        $this->assertEquals($result->name, $fullName);
        $this->assertEquals($result->email, $email);

        $this->assertDatabaseHas('users', [
            'name' => $fullName,
            'email' => $email,
            'ref_code' => null,
            'status' => User::STATUS_ACTIVE,
            'facebook_user_id' => $socialiteUserData->id,
            'facebook_access_token' => $socialiteUserData->token,
        ]);
    }

    /**
     * @test
     * that createUser doesn't creates user in the database if an exception is thrown
     *
     * @covers ::createUser
     */
    public function createUser_whenExceptionIsThrown_doesNotStoreUserInTheDB()
    {
        $firstName = 'test';
        $lastName = 'test';
        $email = 'test@gmail.com';

        $requestMock = $this->createMock(Request::class);
        $requestMock->expects(static::exactly(1))->method('session')->willThrowException(new Exception());

        $result = User::createUser($requestMock);

        $fullName = "$firstName $lastName";

        $this->assertInstanceOf(User::class, $result);

        $this->assertDatabaseMissing('users', [
            'name' =>  $fullName,
            'email' => $email,
            'ref_code' => null,
        ]);
    }

    /**
     * @test
     * that getSubscription always returns {@see \App\User::getSubscriptionDetails} data
     *
     * @covers ::getSubscription
     */
    public function getSubscription_always_returnsSubscriptionDetails()
    {
        $user = User::factory()->create(['stripe_id' => 'cus_312312das23']);

        $stripeCustomerMock = $this->mock(Customer::class);

        $subscriptionStub = $this->getSubscriptionDetailsStubSetUp();
        $this->mock(Product::class)
            ->shouldReceive('retrieve')
            ->withArgs([$subscriptionStub->subscriptions->data[0]->plan->product])
            ->andReturn((object)['name' => $subscriptionStub->subscriptions->data[0]->plan->nickname]);
        $stripeCustomerMock->shouldReceive('retrieve')->andReturn($subscriptionStub);

        $result = app(User::class)->getSubscription($user->stripe_id);

        $expectedSubscriptionData = $subscriptionStub->subscriptions->data[0];
        $this->assertEquals($expectedSubscriptionData->id, $result->stripe_id);
        $this->assertEquals($expectedSubscriptionData->status, $result->stripe_status);
        $this->assertEquals($expectedSubscriptionData->plan->id, $result->stripe_plan);
        $this->assertEquals($expectedSubscriptionData->plan->nickname, $result->name);
        $this->assertEquals($expectedSubscriptionData->current_period_start, $result->current_period_start);
        $this->assertEquals($expectedSubscriptionData->current_period_end, $result->current_period_end);
        $this->assertEquals($expectedSubscriptionData->cancel_at, $result->ends_at);
        $this->assertEquals($expectedSubscriptionData->trial_end, $result->trial_ends_at);
        $this->assertEquals($expectedSubscriptionData->quantity, $result->quantity);
    }

    /**
     * test that the given method returns a Stripe credential from the session
     * that is appropriate for the provided Stripe ID
     *
     * @param string $method that will be tested
     * @param ?string $stripeId of the user
     * @param string $expectedResult of the tested method call
     */
    private function getStripeCredentialMethod_withProvidedId_returnsExpectedCredential(
        string $method,
        ?string $stripeId,
        string $expectedResult
    ) {
        $stripeCredential = User::$method($stripeId);
        $this->assertEquals($expectedResult, $stripeCredential);
    }

    /**
     * @test
     * that getStripeSecret returns stored item in the stripe_secret session key when
     * it is set
     *
     * @covers ::getStripeSecret
     */
    public function getStripeSecret_withSecretAlreadyInSession_returnsSecretFromSession()
    {
        $expectedResult = 'sk_test_31231213fs2df232';

        $this->withSession(['stripe_secret' => $expectedResult]);

        $this->getStripeCredentialMethod_withProvidedId_returnsExpectedCredential(
            $method = 'getStripeSecret',
            $stripeId = null,
            $expectedResult
        );

        $this->getStripeCredentialMethod_withProvidedId_returnsExpectedCredential(
            $method = 'getStripeSecret',
            $stripeId = 'unused_since_the_secret_is_already_in_the_session',
            $expectedResult
        );
    }

    /**
     * @test
     * that getStripePublishKey returns stored item in the stripe_publish_key session key
     * when that item has been saved to the session
     *
     * @covers ::getStripePublishKey
     */
    public function getStripePublishKey_withKeyAlreadyInSession_returnsKeyFromSession()
    {
        $expectedResult = 'pk_test_31231213fs3d2d2';

        $this->withSession(['stripe_publish_key' => $expectedResult]);

        $this->getStripeCredentialMethod_withProvidedId_returnsExpectedCredential(
            $method = 'getStripePublishKey',
            $stripeId = null,
            $expectedResult
        );

        $this->getStripeCredentialMethod_withProvidedId_returnsExpectedCredential(
            $method = 'getStripePublishKey',
            $stripeId = 'unused_since_the_secret_is_already_in_the_session',
            $expectedResult
        );
    }

    /**
     * @test
     * that getStripeSecret returns the default secret when
     * no Stripe ID is provided and the session key is not set
     *
     * @covers ::getStripeSecret
     */
    public function getStripeSecret_withNoStripeId_returnsDefaultSecret()
    {
        $this->flushSession();

        $this->getStripeCredentialMethod_withProvidedId_returnsExpectedCredential(
            $method = 'getStripeSecret',
            $stripeId = null,
            $expectedResult = 'sk_test_Bd7oGsNG67VYznirTNMQy6yJ00l9as5D64', // env('STRIPE_SECRET')
        );
    }

    /**
     * @test
     * that getStripePublishKey returns the default publishable key when
     * no Stripe ID is provided and the session key is not set
     *
     * @covers ::getStripePublishKey
     */
    public function getStripePublishKey_withNoStripeId_returnsDefaultPublishKey()
    {
        $this->flushSession();

        $this->getStripeCredentialMethod_withProvidedId_returnsExpectedCredential(
            $method = 'getStripePublishKey',
            $stripeId = null,
            $expectedResult = 'pk_test_C5eLw1jTqXU3AVepplme3inB00Y7AYZWxy' // env('STRIPE_KEY')
        );
    }

    /**
     * test that the given method returns a Stripe credential from the session
     * that is appropriate for the provided Stripe account type
     *
     * @param string $method that will be tested
     * @param string $stripeAccount represents type of the Stripe merchant that declares customer's stripe keys
     * @param string $expectedResult of the tested method call
     */
    private function getStripeCredentialMethod_withStripeAccountType_returnsExpectedCredential(
        string $method,
        string $stripeAccount,
        string $expectedResult
    ) {
        $stripeId = 'cus_3jk442jnj342';
        User::factory()->create([
            'stripe_id' => $stripeId,
            'stripe_account' => $stripeAccount,
        ]);

        $this->getStripeCredentialMethod_withProvidedId_returnsExpectedCredential(
            $method,
            $stripeId,
            $expectedResult
        );
    }

    /**
     * @test
     * that getStripeSecret returns Stripe secret according to the provided stripe account when
     * 1. The user is found with the provided Stripe ID
     * 2. The Stripe secret is not set in the session
     * and that after retrieval, it sets the secret to the session
     *
     * @covers ::getStripeSecret
     *
     * @dataProvider getStripeSecret_withVariousStripeAccountsProvider
     *
     * @param string $stripeAccount represents type of the Stripe merchant that declares customer's stripe keys
     * @param string $expectedResult of the tested method call
     */
    public function getStripeSecret_withVariousStripeAccounts_returnsExpectedSecret(
        string $stripeAccount,
        string $expectedResult
    ) {
        $this->flushSession();

        $this->assertNull(session('stripe_secret'));

        $this->getStripeCredentialMethod_withStripeAccountType_returnsExpectedCredential(
            $method = 'getStripeSecret',
            $stripeAccount,
            $expectedResult
        );

        $this->assertEquals($expectedResult, session('stripe_secret'));
    }

    /**
     * Data provider for {@see getStripeSecret_withVariousStripeAccounts_returnsExpectedSecret}
     *
     * @return string[][] with stripe account type and expected result of the tested method call
     */
    public function getStripeSecret_withVariousStripeAccountsProvider(): array
    {
        return [
            [
                'stripeAccount' => 'default',
                'expectedResult' => 'sk_test_Bd7oGsNG67VYznirTNMQy6yJ00l9as5D64', // env('STRIPE_SECRET')
            ],
            [
                'stripeAccount' => 'new',
                'expectedResult' => 'sk_test_Bd7oGsNG67V534das2312dYznirTNMQy6yJ00l9as5D64', // env('STRIPE_NEW_SECRET')
            ],
        ];
    }

    /**
     * @test
     * that getStripePublishKey returns Stripe publish key according to the provided stripe account when
     * 1. user is found with the provided Stripe ID
     * 2. The Stripe publishable key is not set in the session
     * and that after retrieval, it sets the publishable key to the session
     *
     * @covers ::getStripePublishKey
     *
     * @dataProvider getStripePublishKey_withVariousStripeAccountsProvider
     *
     * @param string $stripeAccount represents type of the Stripe merchant that declares customer's stripe keys
     * @param string $expectedResult of the tested method call
     */
    public function getStripePublishKey_withVariousStripeAccounts_returnsExpectedPublishKey(
        string $stripeAccount,
        string $expectedResult
    ) {
        $this->flushSession();

        $this->assertNull(session('stripe_publish_key'));

        $this->getStripeCredentialMethod_withStripeAccountType_returnsExpectedCredential(
            $method = 'getStripePublishKey',
            $stripeAccount,
            $expectedResult
        );

        $this->assertEquals($expectedResult, session('stripe_publish_key'));
    }

    /**
     * Data provider for {@see getStripePublishKey_withVariousStripeAccounts_returnsExpectedPublishKey}
     *
     * @return string[][] with stripe account type and expected result of the tested method call
     */
    public function getStripePublishKey_withVariousStripeAccountsProvider(): array
    {
        return [
           [
                'stripeAccount' => 'default',
                'expectedResult' => 'pk_test_C5eLw1jTqXU3AVepplme3inB00Y7AYZWxy', // env('STRIPE_KEY')
            ],
            [
                'stripeAccount' => 'new',
                'expectedResult' => 'pk_test_C5eLw1jTqXU34343AVepplme3inB00Y7AYZWxy', // env('STRIPE_NEW_KEY')
            ],
        ];
    }

    /**
     * @test
     * that getSubscriptionDetails returns subscription details when passed stripe customer id
     *
     * @covers ::getSubscriptionDetails
     */
    public function getSubscriptionDetails_withProvidedStripeId_returnsSubscriptionDetails()
    {
        $user = User::factory()->create(['stripe_id' => 'cus_312312das23']);

        $stripeCustomerMock = $this->mock(Customer::class);
        $subscriptionStub = $this->getSubscriptionDetailsStubSetUp();
        $this->mock(Product::class)
            ->shouldReceive('retrieve')
            ->withArgs([$subscriptionStub->subscriptions->data[0]->plan->product])
            ->andReturn((object)['name' => $subscriptionStub->subscriptions->data[0]->plan->nickname]);
        $stripeCustomerMock->shouldReceive('retrieve')->andReturn($subscriptionStub);

        $result = User::getSubscriptionDetails($user->stripe_id);

        $expectedSubscriptionData = $subscriptionStub->subscriptions->data[0];
        $this->assertEquals($expectedSubscriptionData->id, $result->stripe_id);
        $this->assertEquals($expectedSubscriptionData->status, $result->stripe_status);
        $this->assertEquals($expectedSubscriptionData->plan->id, $result->stripe_plan);
        $this->assertEquals($expectedSubscriptionData->plan->nickname, $result->name);
        $this->assertEquals($expectedSubscriptionData->current_period_start, $result->current_period_start);
        $this->assertEquals($expectedSubscriptionData->current_period_end, $result->current_period_end);
        $this->assertEquals($expectedSubscriptionData->cancel_at, $result->ends_at);
        $this->assertEquals($expectedSubscriptionData->trial_end, $result->trial_ends_at);
        $this->assertEquals($expectedSubscriptionData->quantity, $result->quantity);
    }

    /**
     * @test
     * that getSubscriptionDetails returns subscription details with
     * formatted cancel_at and trial_end in the format 'Y-m-d H:i:s'
     * when cancel_at and trial_end are set
     *
     * @covers ::getSubscriptionDetails
     */
    public function getSubscriptionDetails_withCancelAtAndTrialEnd_returnsTrialSubscriptionDetailsIncludingTrialEnd()
    {
        $user = User::factory()->create(['stripe_id' => 'cus_312312das23']);
        $this->actingAs($user);

        $stripeCustomerMock = $this->mock(Customer::class);

        $subscriptionStub = $this->getSubscriptionDetailsStubSetUp();
        $this->mock(Product::class)
            ->shouldReceive('retrieve')
            ->withArgs([$subscriptionStub->subscriptions->data[0]->plan->product])
            ->andReturn((object)['name' => $subscriptionStub->subscriptions->data[0]->plan->nickname]);
        $subscriptionStub->subscriptions->data[0]->cancel_at = now();
        $subscriptionStub->subscriptions->data[0]->trial_end = now()->subDays(6);

        $stripeCustomerMock->shouldReceive('retrieve')->andReturn($subscriptionStub);

        $result = User::getSubscriptionDetails($user->stripe_id);

        $expectedSubscriptionData = $subscriptionStub->subscriptions->data[0];
        $this->assertEquals($expectedSubscriptionData->id, $result->stripe_id);
        $this->assertEquals($expectedSubscriptionData->status, $result->stripe_status);
        $this->assertEquals($expectedSubscriptionData->plan->id, $result->stripe_plan);
        $this->assertEquals($expectedSubscriptionData->plan->nickname, $result->name);
        $this->assertEquals($expectedSubscriptionData->current_period_start, $result->current_period_start);
        $this->assertEquals($expectedSubscriptionData->current_period_end, $result->current_period_end);
        $this->assertEquals(
            Carbon::parse($expectedSubscriptionData->cancel_at)->format('Y-m-d H:i:s'),
            $result->ends_at
        );
        $this->assertEquals(
            Carbon::parse($expectedSubscriptionData->trial_end)->format('Y-m-d H:i:s'),
            $result->trial_ends_at
        );
        $this->assertEquals($expectedSubscriptionData->quantity, $result->quantity);
    }

    /**
     * @test
     * that getSubscriptionDetails returns null response when passed invalid request
     *
     * @covers ::getSubscriptionDetails
     */
    public function getSubscriptionDetails_withInvalidRequest_returnsNull()
    {
        $user = User::factory()->create(['stripe_id' => 'cus_312312das23']);
        $this->actingAs($user);

        $stripeCustomerMock = $this->mock(Customer::class);

        $subscriptionStub = $this->getSubscriptionDetailsStubSetUp();
        $subscriptionStub->subscriptions->data[0]->cancel_at = true;

        $stripeCustomerMock->shouldReceive('retrieve')->andReturn(new Exception());

        $result = User::getSubscriptionDetails($user->stripe_id);

        $this->assertNull($result);
    }

    /**
     * @test
     * that various methods returns error response when passed invalid provider in the request
     *
     * @covers ::getUserIdFieldForSocialProvider
     * @covers ::getAccessTokenFieldForSocialProvider
     *
     * @dataProvider methods_withVariousMethodsIncludingInvalidProvider
     *
     * @param string $method name of the tested method
     */
    public function methods_withVariousMethodsIncludingInvalidProvider_returnsErrorResponse(
        string $method
    ) {
        $user = User::factory()->create(['stripe_id' => 'cus_312312das23', 'status' => 1]);
        $this->actingAs($user);

        $provider = 'www';

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported social provider');

        User::$method($provider);
    }

    /**
     * Data provider for {@see methods_withVariousMethodsIncludingInvalidProvider_returnsErrorResponse}
     *
     * @return string[][] containing method name
     */
    public function methods_withVariousMethodsIncludingInvalidProvider()
    {
        return [
            ['method' => 'getUserIdFieldForSocialProvider'],
            ['method' => 'getAccessTokenFieldForSocialProvider'],
        ];
    }

    /**
     * @test
     * that getAccessTokenFieldForSocialProvider returns database field for the provider
     *
     * @covers ::getUserIdFieldForSocialProvider
     * @covers ::getAccessTokenFieldForSocialProvider
     *
     * @dataProvider methods_withVariousMethodsProvider
     *
     * @param string $method name that will be tested
     * @param string $provider represents social driver for login
     * @param string $expectedResult of the tested method call
     */
    public function methods_withVariousMethods_returnsProviderField(
        string $method,
        string $provider,
        string $expectedResult
    ) {
        $result = User::$method($provider);

        $this->assertIsString($result);
        $this->assertEquals($expectedResult, $result);
    }

    /**
     * Data provider for {@see methods_withVariousMethods_returnsProviderField}
     *
     * @return string[][] containing
     * method that will be tested
     * provider of the social login
     * expected result of the tested method call
     */
    public function methods_withVariousMethodsProvider()
    {
        return [
            [
                'method' => 'getAccessTokenFieldForSocialProvider',
                'provider' => 'Facebook',
                'expectedResult' => 'facebook_access_token',
            ],
            [
                'method' => 'getUserIdFieldForSocialProvider',
                'provider' => 'Facebook',
                'expectedResult' => 'facebook_user_id',
            ],
        ];
    }

    /**
     * @test
     * that canAddTeamMembers returns true when user allowed to add team members, otherwise false
     *
     * @covers ::canAddTeamMembers
     *
     * @dataProvider canAddTeamMembers_withVariousLimitsProvider
     *
     * @param int $getTotalTeamMemberCount of the logged-in user
     * @param int $stripeModeratorLimit of the user's plan
     * @param bool $expectedResult of the tested method call
     *
     * @throws ApiErrorException if the {@see \Stripe\ApiRequestor::request} fails
     */
    public function canAddTeamMembers_withVariousLimits_returnsTrueResponse(
        int $getTotalTeamMemberCount,
        int $stripeModeratorLimit,
        bool $expectedResult
    ) {
        $user = User::factory()->create(['stripe_id' => 'cus_312312das23']);
        $this->actingAs($user);

        $userMock = $this->getMockBuilder(User::class)
            ->onlyMethods(['getActiveStripePlan', 'getTotalTeamMemberCount'])
            ->setProxyTarget($user)
            ->disableOriginalConstructor()
            ->getMock();

        $plan = (object)[
            'id' => Plan::STRIPE_PLAN_IDS['default']['BASIC'],
            'product' => (object)[
                'metadata' => (object)[
                    'moderator_limit' => $stripeModeratorLimit
                ]
            ],
        ];

        $userMock->expects(static::once())->method('getActiveStripePlan')->willReturn($plan);
        $userMock->expects(static::once())->method('getTotalTeamMemberCount')->willReturn($getTotalTeamMemberCount);

        $result = $userMock->canAddTeamMembers();

        $this->assertIsBool($result);
        $this->assertEquals($expectedResult, $result);
    }

    /**
     * Data provider for {@see canAddTeamMembers_withVariousLimits_returnsTrueResponse}
     *
     * @return array[] containing getTotalTeamMemberCount and expected of the tested method call
     */
    public function canAddTeamMembers_withVariousLimitsProvider(): array
    {
        return [
            'getTotalTeamMemberCount Is Less Than Member Limit' => [
                'getTotalTeamMemberCount' => 25,
                'stripeModeratorLimit' => 30,
                'expectedResult' => true,
            ],
            'getTotalTeamMemberCount Equal The Member limit'  => [
                'getTotalTeamMemberCount' => 15,
                'stripeModeratorLimit' => 15,
                'expectedResult' => false,
            ],
        ];
    }

    /**
     * @test
     * that getActiveStripePlan returns subscribed stripe plan with a product of the user
     *
     * @covers ::getActiveStripePlan
     */
    public function getActiveStripePlan_withInvalidRequest_returnsSubscriptionResponse()
    {
        $owner = User::factory()->create(['stripe_id' => 'cus_312312das23', 'status' => 1]);

        $stripeCustomerMock = $this->mock(Customer::class);
        $subscriptionStub = $this->getSubscriptionDetailsStubSetUp();
        $stripeCustomerMock->shouldReceive('retrieve')->andReturn($subscriptionStub);

        $result = $owner->getActiveStripePlan();

        $expectedSubscriptionData = $subscriptionStub->subscriptions->data[0];
        $this->assertEquals($expectedSubscriptionData->plan->id, $result->id);
        $this->assertEquals($expectedSubscriptionData->plan->nickname, $result->nickname);
        $this->assertEquals($expectedSubscriptionData->plan->nickname, $result->product->name);
    }

    /**
     * @test
     * that canAccessGroup returns true if user can access group
     *
     * @covers ::canAccessGroup
     *
     * @dataProvider canAccessGroup_withVariousUserProvider
     *
     * @param bool $ownGroup indicator that logged in user own group or not
     */
    public function canAccessGroup_withVariousUser_returnsTrueResponse(
        bool $ownGroup
    ) {
        $owner = $this->actingAsUser();

        $facebookGroupsId = 0;

        if ($ownGroup) {
            $facebookGroup = FacebookGroups::factory()->create(['user_id' => $owner->id]);
            TeamMemberGroupAccess::insert([
                'user_id' => $owner->id,
                'facebook_group_id' => $facebookGroup->id,
            ]);
            $facebookGroupsId = $facebookGroup->id;
        }

        $result = $owner->canAccessGroup($facebookGroupsId);

        $this->assertIsBool($result);
        $this->assertEquals($ownGroup, $result);
    }

    /**
     * Data provider for {@see canAccessGroup_withVariousUser_returnsTrueResponse}
     *
     * @return array[] containing indicator is user owns group
     */
    public function canAccessGroup_withVariousUserProvider(): array
    {
        return [
            'User Owns Group' => ['ownGroup' => true],
            'User Does Not Own Group' => ['ownGroup' => false],
        ];
    }
    /**
     * @test
     * that getOwnedGroupByFacebookId returns FacebookGroup instance if the user owns group,
     * otherwise it returns null
     *
     * @covers ::getOwnedGroupByFacebookId
     *
     * @dataProvider getOwnedGroupByFacebookId_withVariousOwnedStatusesProvider
     *
     * @param null|bool $ownGroup true if user owns group, otherwise null
     * @param null|bool $expectedResult of the tested method call
     */
    public function getOwnedGroupByFacebookId_withVariousOwnedStatuses_returnsBoolResult(
        ?bool $ownGroup,
        ?bool $expectedResult
    ) {
        $facebookGroup = FacebookGroups::factory()->make();
        $facebookGroupId = 312312312;

        $userMock = $this->getMockBuilder(User::class)
            ->addMethods(['where', 'first'])
            ->onlyMethods(['groupsOwned'])
            ->getMock();

        $userMock->expects(static::once())->method('groupsOwned')->with()->willReturnSelf();
        $userMock->expects(static::once())->method('where')->with('fb_id', $facebookGroupId)->willReturnSelf();
        $userMock->expects(static::once())->method('first')->willReturn($ownGroup ? $facebookGroup : $ownGroup);

        $this->assertEquals(
            $expectedResult ? $facebookGroup : $expectedResult,
            $userMock->getOwnedGroupByFacebookId($facebookGroupId)
        );
    }

    /**
     * @test
     * that getOwnedGroupByFacebookId returns determine if the user owns the provided Facebook Group by it's Facebook ID
     *
     * @covers ::getOwnedGroupByFacebookId
     */
    public function getOwnedGroupByFacebookId_withValidFacebookGroupId_returnsGroupDetails()
    {
        $user = User::factory()->create(['stripe_id' => 'cus_312312das23']);
        $this->actingAs($user);

        $facebookGroup = FacebookGroups::factory(['user_id' => $user->id])->create();

        GroupMembers::factory(10)->create(
            [
                'user_id' => $user->id,
                'group_id' => $facebookGroup->id,
                'is_approved' => 1,
            ]
        );

        $result = app(User::class)->getOwnedGroupByFacebookId($facebookGroup->fb_id);

        $this->assertNull($result);
    }

    /**
     * Data provider for {@see getOwnedGroupByFacebookId_withVariousOwnedStatuses_returnsBoolResult}
     *
     * @return array[] containing own group property and expected result of the tested method call
     */
    public function getOwnedGroupByFacebookId_withVariousOwnedStatusesProvider(): array
    {
        return [
            ['ownGroup' => null, 'expectedResult' => null],
            ['ownGroup' => true, 'expectedResult' => true],
        ];
    }

    /**
     * @test
     * that getUsersDetails returns details of all the users with their plan details and approvals details
     * when passed valid request parameter
     *
     * @covers ::getUsersDetails
     *
     * @dataProvider getUsersDetails_withValidRequestProvider
     *
     * @param array $request containing request params
     * email will filters records as per email address
     * status will filters records as per users status
     * customer_id_list will filters records as stripe customers id
     * order_column will sorts records as per selected column
     * order_by will navigate direction(asc/desc) for ordering records
     * offset will skipped passed number of records
     * limit will returns passed number of records
     */
    public function getUsersDetails_withValidRequest_returnsUsersResponse(array $request)
    {
        $user = User::factory()->create(
            [
                'name' => 'john doe',
                'email' => 'test@gmail.com',
                'stripe_id' => 'cus_312312das23',
                'status' => 1,
            ]
        );
        $this->actingAs($user);

        $request['email'] = $user->email;
        $request['customers_id_list'] = [$user->stripe_id];

        $request = new Request($request);

        $userMock = $this->partialMock(User::class);

        $result = $userMock->getUsersDetails($request);

        $content = json_decode(json_encode($result));

        $this->assertIsInt(count($content->data));
        $this->assertEquals($content->data[0]->name, $user->name);
    }

    /**
     * Data provider for {@see getUsersDetails_withValidRequest_returnsUsersResponse}
     *
     * @return array[] containing request params
     * 1. email for filtering by email address
     * 2. status for filtering by {@see User} status
     * customer_id_list will filters records as stripe customers id
     * order_column will sorts records as per selected column
     * order_by will navigate direction(asc/desc) for ordering records
     * offset will skipped passed number of records
     * limit will returns passed number of records
     */
    public function getUsersDetails_withValidRequestProvider(): array
    {
        return [
            'Default Request'        => [
                'request' => [
                    'email'             => 'test@gmail.com',
                    'status'            => 1,
                    'customers_id_list' => ['cus_312312das23'],
                ],
            ],
            'Request With No Search' => [
                'request' => [
                    'email'             => 'test@gmail.com',
                    'status'            => 1,
                    'customers_id_list' => ['cus_312312das23'],
                    'order_column'      => 'name',
                    'order_by'          => 'asc',
                ],
            ],
            'Request With Offset'    => [
                'request' => [
                    'email'             => 'test@gmail.com',
                    'status'            => 1,
                    'customers_id_list' => ['cus_312312das23'],
                    'offset'            => 0,
                    'limit'             => 10,
                ],
            ],
        ];
    }

    /**
     * @test
     * that getUsersDetails returns error response of mysql Database QueryException when user passed request parameters
     * In code we are using DATE_FORMAT() which is not managed by sqlite database thus error occurred.
     *
     * @covers ::getUsersDetails
     *
     * @dataProvider getUsersDetails_withInvalidRequestProvider
     *
     * @param array $request containing request params as below
     * status will filters records as per users status
     * searchWithStripeKeywords will managing flag which maintain search criteria as per true/false value
     * to gets data as per stripe_id and others filters are optional
     * order_column will sorts records as per selected column
     * order_by will navigate direction(asc/desc) for ordering records
     */
    public function getUsersDetails_withInvalidRequest_returnsErrorResponse(array $request)
    {
        $user = User::factory()->create(
            [
                'name' => 'Johndoe',
                'email' => 'john.doe@gmail.com',
                'stripe_id' => 'cus_312312das23',
            ]
        );
        $this->actingAs($user);

        $userMock = $this->mock(User::class);
        $userMock->shouldReceive('orderByDesc')->withArgs(['created_at'])->andReturn($user);

        $request['customers_id_list'] = [$user->stripe_id];
        $request['search'] = $user->name;

        $request = new Request($request);

        $this->expectException('Illuminate\Database\QueryException');

        $user->getUsersDetails($request);
    }

    /**
     * Data provider for {@see getUsersDetails_withInvalidRequest_returnsErrorResponse}
     *
     * @return array[] containing request params
     * status will filters records as per users status
     * searchWithStripeKeywords will managing flag which maintain search criteria as per true/false value
     * to gets data as per stripe_id and others filters are optional
     * order_column will sorts records as per selected column
     * order_by will navigate direction(asc/desc) for ordering records
     */

    public function getUsersDetails_withInvalidRequestProvider(): array
    {
        return [
            'Request with search, status, email,customers_id_list,order_column,order_by' .
            ' and without searchWithStripeKeywords' => [
                'request' => [
                    'status' => 0,
                    'searchWithStripeKeywords' => false,
                    'order_column' => 'name',
                    'order_by' => 'asc',
                ],
            ],
            'Request with search,email,customers_id_list, searchWithStripeKeywords and without status' => [
                'request' => [
                    'status' => 1,
                    'searchWithStripeKeywords' => true,
                ],
            ],
        ];
    }

    /**
     * @test
     * that updateUserStatus returns message according to the updated status
     *
     * @covers ::updateUserStatus
     *
     * @dataProvider updateUserStatus_withVariousStatusesProvider
     *
     * @param int $userStatus of the user in the database
     * @param int $updateStatus for the user that will be updated to
     * @param string $expectedMessage of the tested method call
     */
    public function updateUserStatus_withVariousStatuses_returnsMessage(
        int $userStatus,
        int $updateStatus,
        string $expectedMessage
    ) {
        $user = User::factory()->create(['stripe_id' => 'cus_312312das23', 'status' => $userStatus]);

        $subscriptionServiceMock = $this->mock(SubscriptionService::class);
        $subscriptionServiceMock->shouldReceive('recurringPayment')->andReturn(true);

        $requestMock = new Request([
            'user_id'     => $user->id,
            'user_status' => $updateStatus,
        ]);

        $result = app(User::class)->updateUserStatus($requestMock);

        $this->assertIsArray($result);
        $this->assertEquals($expectedMessage, $result['message']);
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => $user->name,
            'stripe_id' => $user->stripe_id,
            'status' => $updateStatus,
        ]);
    }

    /**
     * Data provider for {@see updateUserStatus_withVariousStatuses_returnsMessage}
     *
     * @return array[] containing
     * user status that is created in the database,
     * update status that method should update to,
     * expected message of the tested method call
     */
    public function updateUserStatus_withVariousStatusesProvider()
    {
        return [
            [
                'userStatus' => 0,
                'updateStatus' => 1,
                'expectedMessage' => 'User activated',
            ],
            [
                'userStatus' => 1,
                'updateStatus' => 0,
                'expectedMessage' => 'User deactivated',
            ],
        ];
    }

    /**
     * @test
     * that deleteUser soft deletes user with the provided id
     *
     * @covers ::deleteUser
     */
    public function deleteUser_withValidUser_returnsSuccessResponse()
    {
        $user = User::factory()->create(['stripe_id' => 'cus_312312das23']);

        $subscriptionServiceMock = $this->mock(SubscriptionService::class);
        $subscriptionServiceMock->shouldReceive('cancel')->andReturn(true);

        $result = User::deleteUser($user->id);

        $this->assertIsArray($result);
        $this->assertEquals('User deleted successfully', $result['message']);
        $this->assertSoftDeleted('users', [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
        ]);
    }

    /**
     * @test
     * that getAssociatedTeamMembersList returns all team members of the owner's team
     *
     * @covers ::getAssociatedTeamMembersList
     */
    public function getAssociatedTeamMembersList_always_returnsTeamMembers()
    {
        $owner = User::factory()->create();

        $teamMembers = User::factory(5)->create();

        $ownerTeamMembers = $teamMembers->map(function ($teamMember) use ($owner) {
            return [
                'team_member_id' => $teamMember->id,
                'owner_id' => $owner->id,
            ];
        })->toArray();
        OwnerToTeamMember::insert($ownerTeamMembers);

        $result = User::getAssociatedTeamMembersList($owner->id);

        $teamMembersOrdered = User::whereIn('id', $teamMembers->pluck('id'))->orderByDesc('id')->get();
        for ($i = 0; $i < $teamMembers->count(); $i++) {
            $this->assertEquals($teamMembersOrdered[$i]->id, $result[$i]->id);
            $this->assertEquals($teamMembersOrdered[$i]->name, $result[$i]->name);
            $this->assertEquals($teamMembersOrdered[$i]->email, $result[$i]->email);
        }
    }

    /**
     * @test
     * that getGroupMembersCount returns count of active group members based
     * on ownerId & groupId from the group_members table.
     *
     * @covers ::getGroupMembersCount
     */
    public function getGroupMembersCount_always_returnsGroupMembersCount()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $facebookGroup = FacebookGroups::factory(['user_id' => $user->id])->create();

        $ownedGroupMembers = GroupMembers::factory(10)->create(
            [
                'user_id'     => $user->id,
                'group_id'    => $facebookGroup->id,
                'is_approved' => 1,
            ]
        );

        GroupMembers::factory(20)->create(['is_approved' => 1]);

        $result = User::getGroupMembersCount($user->id, $facebookGroup->id);

        $this->assertIsInt($result);
        $this->assertEquals($ownedGroupMembers->count(), $result);
    }

    /**
     * @test
     * that getMembersApprovals returns approvals count for Facebook group members between
     * the subscription start date and subscription end date
     *
     * @covers ::getMembersApprovals
     */
    public function getMembersApprovals_always_returnsMembersApprovalsCount()
    {
        $user = User::factory()->create(['stripe_id' => 'cus_312312das23']);
        $this->actingAs($user);

        $facebookGroup = FacebookGroups::factory(['user_id' => $user->id])->create();

        $groups = GroupMembers::factory(10)->create(
            [
                'user_id'     => $user->id,
                'group_id'    => $facebookGroup->id,
                'is_approved' => 1,
            ]
        );

        $startDate = date('Y-m-d');
        $endDate = date('Y-m-d', strtotime('1 Month'));

        $result = User::getMembersApprovals($user->id, $startDate, $endDate);

        $this->assertIsInt($result);
        $this->assertEquals($groups->count(), $result);
    }

    /**
     * @test
     * that getUserGroupDetails returns total group members approval and Facebook group URL
     * for each group that the user has access to
     *
     * @covers ::getUserGroupDetails
     */
    public function getUserGroupDetails_always_returnsUserGroupDetails()
    {
        $user = User::factory()->create(['stripe_id' => 'cus_312312das23']);

        $facebookGroups = FacebookGroups::factory(3)->create(['user_id' => $user->id]);

        $approvedMembers = [];
        foreach ($facebookGroups as $facebookGroup) {
            $approvedMembers[] = GroupMembers::factory(rand(1, 10))->create(
                [
                    'user_id'     => $user->id,
                    'group_id'    => $facebookGroup->id,
                    'is_approved' => 1,
                ]
            );

            GroupMembers::factory(rand(1, 10))->create(
                [
                    'user_id'     => $user->id,
                    'group_id'    => $facebookGroup->id,
                    'is_approved' => 0,
                ]
            );
        }

        $result = User::getUserGroupDetails($user, (object)['user_id' => $user->id]);

        for ($i = 0; $i < $facebookGroups->count(); $i++) {
            $this->assertEquals($approvedMembers[$i]->count(), $result[$i]->totalGroupMembersApprovals);
            $this->assertEquals(
                config('const')['GROUP_LINK'] . $facebookGroups[$i]->fb_id,
                $result[$i]->groupLink
            );
        }
    }

    /**
     * @test
     * that addTeamMember stores team member in the owner's team and sent him welcome team member email
     *
     * @covers ::addTeamMember
     */
    public function addTeamMember_withTeamMemberWithoutAssignedFacebookGroups_storesTeamMember()
    {
        $owner = User::factory()->create();

        Mail::fake();

        $teamMember = [
            'name' => 'test',
            'email' => 'test@gmail.com',
        ];

        $result = $owner->addTeamMember($owner->id, $teamMember);

        $this->assertEquals('Invite sent to team member successfully.', $result['message']);
        $this->assertEquals(true, $result['success']);

        Mail::assertSent(TeamMemberMail::class);

        $this->assertDatabaseHas('users', [
            'name' => $teamMember['name'],
            'email' => $teamMember['email'],
        ]);

        $storedTeamMember = User::where('email', $teamMember['email'])
            ->where('name', $teamMember['name'])
            ->first();
        $this->assertDatabaseHas('owner_to_team_members', [
            'owner_id' =>  $owner->id,
            'team_member_id' => $storedTeamMember->id,
        ]);
    }

    /**
     * @test
     * that addTeamMember returns error response when team member already exists in the owner's team
     *
     * @covers ::addTeamMember
     */
    public function addTeamMember_withExistingUserInTheTeam_returnsErrorMessage()
    {
        Mail::fake();

        $user = User::factory()->create();

        $teamMember = User::factory()->create(['email' => 'test@gmail.com']);

        $ownerToTeamMembersData = [
            'team_member_id' => $teamMember->id,
            'owner_id'       => $user->id,
        ];
        OwnerToTeamMember::factory()->create($ownerToTeamMembersData);

        $requestedTeamMember = [
            'name'  => 'test',
            'email' => 'test@gmail.com',
        ];

        $result = app(User::class)->addTeamMember($user->id, $requestedTeamMember);

        $this->assertEquals('The member already exists in your team.', $result['message']);
        $this->assertEquals(false, $result['success']);

        Mail::assertNothingSent();
    }

    /**
     * @test
     * that addTeamMember:
     * 1. stores team member in the owner's team
     * 2. assigns team member group access to the provided facebook groups id
     * 3. sent him welcome team member email
     *
     * @covers ::addTeamMember
     */
    public function addTeamMember_withProvidedFacebookGroups_assignsFacebookGroupsToTeamMember()
    {
        Mail::fake();

        $owner = User::factory()->create();

        $facebookGroups = FacebookGroups::factory(5)->create(['user_id' => $owner->id]);

        $teamMember = [
            'facebook_groups_id' => $facebookGroups->pluck('id')->toArray(),
            'name' => 'test',
            'email' => 'test@gmail.com',
        ];

        $result = app(User::class)->addTeamMember($owner->id, $teamMember);

        $this->assertEquals('Invite sent to team member successfully.', $result['message']);
        $this->assertEquals(true, $result['success']);

        Mail::assertSent(TeamMemberMail::class);

        $this->assertDatabaseHas('users', [
            'name' => $teamMember['name'],
            'email' => $teamMember['email'],
        ]);

        $storedTeamMember = User::where('email', $teamMember['email'])
            ->where('name', $teamMember['name'])
            ->first();
        $this->assertDatabaseHas('owner_to_team_members', [
            'owner_id' =>  $owner->id,
            'team_member_id' => $storedTeamMember->id,
        ]);

        foreach ($facebookGroups as $facebookGroup) {
            $this->assertDatabaseHas('team_member_group_access', [
                'user_id' => $storedTeamMember->id,
                'facebook_group_id' => $facebookGroup->id,
            ]);
        }
    }

    /**
     * @test
     * that addTeamMember returns error response when user passed invalid request
     *
     * @covers ::addTeamMember
     */
    public function addTeamMember_withInvalidRequest_returnsErrorResponse()
    {
        $user = User::factory()->create(['stripe_id' => 'cus_312312das23']);
        $this->actingAs($user);

        $teamMember = [
            'facebook_groups_id' => [1],
            'name'               => 'test',
            'email'              => 'test@gmail.com',
        ];

        $result = app(User::class)->addTeamMember($user->id, $teamMember);

        $this->assertEquals('Unable To Add Team Member.', $result['message']);
        $this->assertEquals(false, $result['success']);
    }

    /**
     * @test
     * that getTotalApproveMembersCount returns total members approval count for all passed users
     *
     * @covers ::getTotalApproveMembersCount
     */
    public function getTotalApproveMembersCount_withUserIdAndPeriods_returnsUsersApprovalCount()
    {
        $user = User::factory()->create();
        $facebookGroup = FacebookGroups::factory(['user_id' => $user->id])->create();
        $groupMembers = GroupMembers::factory(10)->create(
            [
                'user_id'     => $user->id,
                'group_id'    => $facebookGroup->id,
                'is_approved' => 1,
            ]
        );

        $requestData = [
            [
                'user_id'      => $user->id,
                'period_start' => date('Y-m-d'),
                'period_end'   => date('Y-m-d', strtotime('1 Month')),
            ]
        ];

        $result = User::getTotalApproveMembersCount($requestData);

        $this->assertEquals($user->id, $result[0]['user_id']);
        $this->assertEquals($groupMembers->count(), $result[0]['count']);
    }

    /**
     * @test
     * that getTotalApproveMembersCount returns total members approval count for all passed users
     *
     * @covers ::getTotalApproveMembersCount
     */
    public function getTotalApproveMembersCount_withoutPeriods_returnsUsersWithoutApprovedMembersCount()
    {
        $user = User::factory()->create();
        $facebookGroup = FacebookGroups::factory(['user_id' => $user->id])->create();
        GroupMembers::factory(10)->create(
            [
                'user_id'     => $user->id,
                'group_id'    => $facebookGroup->id,
                'is_approved' => 1,
            ]
        );

        $requestData = [['user_id' => $user->id]];

        $result = User::getTotalApproveMembersCount($requestData);

        $this->assertEquals($user->id, $result[0]['user_id']);
        $this->assertArrayNotHasKey('count', $result[0]);
    }

    /**
     * @test
     * that getUsersDetails returns details of all the users with their plan details and approvals details
     * based on searchWithStripeKeywords flag and valid request parameter
     *
     * @covers ::getUsersDetails
     *
     * @dataProvider getUsersDetails_withSearchWithStripeKeywordsFlagProvider
     *
     * @param bool $searchWithStripeKeywords contains boolean value
     * @param array $expectedResponse contains expected response
     * searchWithStripeKeywords will managing flag which maintain search criteria as per true/false value
     * to gets data as per stripe_id and others filters are optional
     */
    public function getUsersDetails_withSearchWithStripeKeywordsFlag_returnsUsersResponse(
        bool $searchWithStripeKeywords,
        array $expectedResponse
    ) {
        User::factory()->create(
            [
                'name' => 'john smith',
                'email' => 'johnsmith@gmail.com',
                'stripe_id' => 'cus_312312das13',
                'status' => 1,
            ]
        );
        $user = User::factory()->create(
            [
                'name' => 'john doe',
                'email' => 'johndoe@gmail.com',
                'stripe_id' => 'cus_312312das23',
                'status' => 1,
            ]
        );
        $this->actingAs($user);

        $request['customers_id_list'] = [$user->stripe_id];
        $request['search'] = $user->name;
        $request['searchWithStripeKeywords'] = $searchWithStripeKeywords;

        $request = new Request($request);

        $userMock = $this->partialMock(User::class);

        $this->addMySQLDATE_FORMATFunction();

        $result = $userMock->getUsersDetails($request);

        $content = json_decode(json_encode($result));

        /**
         * creating custom array & comparing returned response with expected response
         */
        $responseUsers = [];
        foreach ($content->data as $user) {
            $responseUsers[] = [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'stripe_id' => $user->stripe_id,
                'status' => $user->status,
            ];
        }

        $this->assertEquals($expectedResponse, $responseUsers);
        $this->assertCount(count($expectedResponse), $content->data);
    }

    /**
     * Data provider for {@see getUsersDetails_withSearchWithStripeKeywordsFlag_returnsUsersResponse}
     *
     * @return array[] containing searchWithStripeKeywords,response
     * searchWithStripeKeywords will managing flag which maintain search criteria as per true/false value
     * to gets data as per stripe_id and others filters are optional
     * response which contains expected response.
     */
    public function getUsersDetails_withSearchWithStripeKeywordsFlagProvider(): array
    {
        return [
            'Request Without searchWithStripeKeywords' => [
                'searchWithStripeKeywords' => false,
                'response' => [
                    [
                        'id' => 1,
                        'name' => 'john smith',
                        'email' => 'johnsmith@gmail.com',
                        'stripe_id' => 'cus_312312das13',
                        'status' => '1',
                    ],
                    [
                        'id' => 2,
                        'name' => 'john doe',
                        'email' => 'johndoe@gmail.com',
                        'stripe_id' => 'cus_312312das23',
                        'status' => '1',
                    ],
                ],
            ],
            'Request With searchWithStripeKeywords' => [
                'searchWithStripeKeywords' => true,
                'response' => [
                    [
                        'id' => 2,
                        'name' => 'john doe',
                        'email' => 'johndoe@gmail.com',
                        'stripe_id' => 'cus_312312das23',
                        'status' => '1',
                    ],
                ],
            ],
        ];
    }
    /**
     * @test
     * that hasProPlan returns true when the user has a GroupKit Pro plan, otherwise false
     *
     * @covers ::hasProPlan
     *
     * @dataProvider hasProPlan_whenTheUserHasAProPlanProvider
     *
     * @param string $stripePlanId from Stripe that the User is subscribed to
     * @param bool $expectedResult of the tested method call
     */
    public function hasProPlan_whenTheUserHasAProPlan_returnsTrueResponse(string $stripePlanId, bool $expectedResult)
    {
        $subscriptionStub = $this->getSubscriptionDetailsStubSetUp();

        $user = User::factory()->create(['stripe_id' => 'cus_JnOU3mPaXoRLia']);
        $this->actingAs($user);

        $subscriptionStub->subscriptions->data[0]->plan->id = $stripePlanId;
        $stripeCustomerMock = $this->mock(Customer::class);
        $stripeCustomerMock->shouldReceive('retrieve')->andReturn($subscriptionStub);

        $result = $user->hasProPlan();

        $this->assertIsBool($result);
        $this->assertEquals($expectedResult, $result);
    }

    /**
     * Data provider for {@see hasProPlan_whenTheUserHasAProPlan_returnsTrueResponse}
     *
     * @return string[][] containing Stripe plan id and expected result of the tested method
     */
    public function hasProPlan_whenTheUserHasAProPlanProvider(): array
    {
        return [
            'Request With expected plan id' => [
                'stripePlanId' => Plan::STRIPE_PLAN_IDS['default']['PRO_MONTHLY'],
                'expectedResult' => true,
            ],
            'Request Without expected plan id' => [
                'stripePlanId' => Plan::STRIPE_PLAN_IDS['default']['BASIC'],
                'expectedResult' => false,
            ],
        ];
    }

    /**
     * @test
     * that hasBasicPlan returns true when the user has a GroupKit Basic plan, otherwise false
     *
     * @covers ::hasBasicPlan
     *
     * @dataProvider hasBasicPlan_whenTheUserHasABasicPlanProvider
     *
     * @param string $stripePlanId from Stripe that the User is subscribed to
     * @param bool $expectedResult of the tested method call
     */
    public function hasBasicPlan_whenTheUserHasABasicPlan_returnsTrueResponse(
        string $stripePlanId,
        bool $expectedResult
    ) {
        $subscriptionStub = $this->getSubscriptionDetailsStubSetUp();

        $user = User::factory()->create(['stripe_id' => 'cus_JnOU3mPaXoRLia']);
        $this->actingAs($user);

        $subscriptionStub->subscriptions->data[0]->plan->id = $stripePlanId;
        $stripeCustomerMock = $this->mock(Customer::class);
        $stripeCustomerMock->shouldReceive('retrieve')->andReturn($subscriptionStub);

        $result = $user->hasBasicPlan();

        $this->assertIsBool($result);
        $this->assertEquals($expectedResult, $result);
    }

    /**
     * Data provider for {@see hasBasicPlan_whenTheUserHasABasicPlan_returnsTrueResponse}
     *
     * @return string[][] containing Stripe plan id and expected result of the method
     */
    public function hasBasicPlan_whenTheUserHasABasicPlanProvider(): array
    {
        return [
            'Request With expected plan id' => [
                'stripePlanId' => Plan::STRIPE_PLAN_IDS['default']['BASIC'],
                'expectedResult' => true,
            ],
            'Request Without expected plan id' => [
                'stripePlanId' => Plan::STRIPE_PLAN_IDS['default']['PRO_MONTHLY'],
                'expectedResult' => false,
            ],
        ];
    }

    /**
     * @test
     * that planIsNotAvailable returns true when the user does not have an active plan, otherwise false
     *
     * @covers ::planIsNotAvailable
     *
     * @dataProvider planIsNotAvailable_whenTheUserDoesNotHaveAnActivePlanProvider
     *
     * @param string $stripeId from Stripe that the User is subscribed to
     * @param string $stripePlanId from Stripe that the User is subscribed to
     * @param bool $expectedResult of the tested method call
     */
    public function planIsNotAvailable_whenTheUserDoesNotHaveAnActivePlan_returnsTrueResponse(
        string $stripeId,
        string $stripePlanId,
        bool $expectedResult
    ) {
        $subscriptionStub = $this->getSubscriptionDetailsStubSetUp();

        $user = User::factory()->create(['stripe_id' => $stripeId]);
        $this->actingAs($user);

        $subscriptionStub->subscriptions->data[0]->plan->id = $stripePlanId;
        $stripeCustomerMock = $this->mock(Customer::class);
        $stripeCustomerMock->shouldReceive('retrieve')->andReturn($subscriptionStub);

        $result = $user->planIsNotAvailable();

        $this->assertIsBool($result);
        $this->assertEquals($expectedResult, $result);
    }

    /**
     * Data provider for {@see planIsNotAvailable_whenTheUserDoesNotHaveAnActivePlan_returnsTrueResponse}
     *
     * @return string[][] containing Stripe customer id, Stripe plan id and expected result of the method
     */
    public function planIsNotAvailable_whenTheUserDoesNotHaveAnActivePlanProvider(): array
    {
        return [
            'Request with active plan' => [
                'stripeId' => 'cus_JnOU3mPaXoRLia',
                'stripePlanId' => Plan::STRIPE_PLAN_IDS['default']['BASIC'],
                'expectedResult' => false,
            ],
            'Request without active plan' => [
                'stripeId' => 'cus_JnOU3mPaXoRLia',
                'stripePlanId' => Plan::STRIPE_PLAN_IDS['new']['BASIC'],
                'expectedResult' => true,
            ],
            'Request without Stripe Id' => [
                'stripeId' => '',
                'stripePlanId' => Plan::STRIPE_PLAN_IDS['default']['BASIC'],
                'expectedResult' => true,
            ],
        ];
    }



    /**
     * @test
     * that getStripePlanId returns true when the user has an active plan, otherwise false
     *
     * @covers ::getStripePlanId
     *
     * @dataProvider getStripePlanId_whenTheUserHasOrDoesNotHaveAnActivePlanProvider
     *
     * @param string $stripeId from Stripe that the User is subscribed to
     * @param string $stripePlanId from Stripe that the User is subscribed to
     * @param string|null $expectedResult of the tested method call
     */
    public function getStripePlanId_whenTheUserHasOrDoesNotHaveAnActivePlan_returnsPlanKeyResponse(
        string $stripeId,
        string $stripePlanId,
        ?string $expectedResult
    ) {
        $subscriptionStub = $this->getSubscriptionDetailsStubSetUp();

        $user = User::factory()->create(['stripe_id' => $stripeId]);
        $this->actingAs($user);

        $subscriptionStub->subscriptions->data[0]->plan->id = $stripePlanId;
        $stripeCustomerMock = $this->mock(Customer::class);
        $stripeCustomerMock->shouldReceive('retrieve')->andReturn($subscriptionStub);

        $result = $user->getStripePlanId();

        $this->assertEquals($expectedResult, $result);
    }

    /**
     * Data provider for {@see getStripePlanId_whenTheUserHasOrDoesNotHaveAnActivePlan_returnsPlanKeyResponse}
     *
     * @return string[][] containing Stripe customer id, Stripe plan id and expected result of the method
     */
    public function getStripePlanId_whenTheUserHasOrDoesNotHaveAnActivePlanProvider(): array
    {
        return [
            'Request with active plan' => [
                'stripeId' => 'cus_JnOU3mPaXoRLia',
                'stripePlanId' => Plan::STRIPE_PLAN_IDS['default']['BASIC'],
                'expectedResult' => Plan::STRIPE_PLAN_IDS['default']['BASIC'],
            ],
            'Request without Stripe Id' => [
                'stripeId' => '',
                'stripePlanId' => Plan::STRIPE_PLAN_IDS['default']['BASIC'],
                'expectedResult' => null,
            ],
        ];
    }

    /**
     * @test
     * that getGroupIdWithMostRecentlyAddedMember returns group id of most recently added members.
     *
     * @covers ::getGroupIdWithMostRecentlyAddedMember
     */
    public function getGroupIdWithMostRecentlyAddedMember_always_returnsGroupResponse()
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $lastFacebookGroupId = '';

        $facebookGroups = FacebookGroups::factory(5)
            ->create(
                [
                    'user_id' => $user->id,
                    'deleted_at' => null,
                ]
            );

        foreach ($facebookGroups as $facebookGroup) {
            $lastFacebookGroupId = $facebookGroup->id;
            GroupMembers::factory(3)->create(
                [
                    'user_id' => $user->id,
                    'group_id' => $lastFacebookGroupId,
                    'is_approved' => 1,
                    'deleted_at' => null,
                ]
            );
        }
        $result = User::getGroupIdWithMostRecentlyAddedMember($user->id);

        $this->assertIsString($result);
        $this->assertEquals($lastFacebookGroupId, $result);
    }

    /**
     * @test
     * that groupColumnsSettings returns {@see BelongsToMany} relationship for {@see FacebookGroups} and {@see User}
     *
     * @covers ::groupColumnsSettings
     */
    public function groupColumnsSettings_always_returnsBelongsToMany()
    {
        $belongsToMock = $this->createPartialMock(BelongsToMany::class, ['as', 'withPivot']);
        $currentMock = $this->createPartialMock(User::class, ['belongsToMany']);
        $currentMock->expects(static::once())
            ->method('belongsToMany')
            ->with(FacebookGroups::class, 'group_settings', 'user_id', 'group_id')
            ->willReturn($belongsToMock);

        $belongsToMock->expects(static::once())->method('as')->with('columnsVisibility')->willReturnSelf();
        $belongsToMock->expects(static::once())->method('withPivot')->with('columns_visibility')->willReturnSelf();

        $result = $currentMock->groupColumnsSettings();

        $this->assertInstanceOf(BelongsToMany::class, $result);
        $this->assertEquals($belongsToMock, $result);
    }

    /**
     * @test
     * that groupSettings returns {@see BelongsToMany} relationship for {@see FacebookGroups} and {@see User}
     *
     * @covers ::groupSettings
     */
    public function groupSettings_always_returnsBelongsToMany()
    {
        $belongsToMock = $this->createPartialMock(BelongsToMany::class, ['as', 'withPivot']);
        $currentMock = $this->createPartialMock(User::class, ['belongsToMany']);
        $currentMock->expects(static::once())
            ->method('belongsToMany')
            ->with(FacebookGroups::class, 'group_settings', 'user_id', 'group_id')
            ->willReturn($belongsToMock);

        $belongsToMock->expects(static::once())->method('as')->with('groupSettings')->willReturnSelf();
        $belongsToMock->expects(static::once())
            ->method('withPivot')
            ->with(['columns_visibility', 'columns_width'])
            ->willReturnSelf();

        $result = $currentMock->groupSettings();

        $this->assertInstanceOf(BelongsToMany::class, $result);
        $this->assertEquals($belongsToMock, $result);
    }

    /**
     * @test
     * that getColumnsSettingsByGroup returns null when there is no results with provided group id
     *
     * @covers ::getColumnsSettingsByGroup
     */
    public function getColumnsSettingsByGroup_whenThereIsNoFacebookGroup_returnsNull()
    {
        $groupId = 1;
        $currentMock = $this->createPartialMock(User::class, ['groupColumnsSettings']);
        $belongsToManyMock = $this->getMockBuilder(BelongsToMany::class)
            ->disableOriginalConstructor()
            ->addMethods(['where'])
            ->getMock();
        $facebookGroupMock = $this->getMockBuilder(FacebookGroups::class)->addMethods(['first'])->getMock();
        $currentMock->expects(static::once())
            ->method('groupColumnsSettings')
            ->willReturn($belongsToManyMock);

        $belongsToManyMock->expects(static::once())
            ->method('where')
            ->with('group_id', $groupId)
            ->willReturn($facebookGroupMock);
        $facebookGroupMock->expects(static::once())->method('first')->willReturn(null);

        $result = $currentMock->getColumnsSettingsByGroup($groupId);

        $this->assertNull($result);
    }

    /**
     * @test
     * that getColumnsSettingsByGroup returns Facebook group instance with the columns
     *
     * @covers ::getColumnsSettingsByGroup
     */
    public function getColumnsSettingsByGroup_happyPath_returnsColumnsSettings()
    {
        $groupId = 1;
        $currentMock = $this->createPartialMock(User::class, ['groupColumnsSettings']);
        $belongsToManyMock = $this->getMockBuilder(BelongsToMany::class)
            ->disableOriginalConstructor()
            ->addMethods(['where'])
            ->getMock();
        $facebookGroupMock = $this->getMockBuilder(FacebookGroups::class)->addMethods(['first'])->getMock();
        $currentMock->expects(static::once())
            ->method('groupColumnsSettings')
            ->willReturn($belongsToManyMock);

        $belongsToManyMock->expects(static::once())
            ->method('where')
            ->with('group_id', $groupId)
            ->willReturn($facebookGroupMock);
        $facebookGroupMock->setAttribute('id', $groupId);
        $facebookGroupMock->setAttribute(
            'columns_visibility',
            ['columns_visibility' => GroupControllerTest::COLUMNS_VISIBILITY]
        );
        $facebookGroupMock->expects(static::once())->method('first')->willReturnSelf();

        $result = $currentMock->getColumnsSettingsByGroup($groupId);
        $this->assertInstanceOf(FacebookGroups::class, $result);
        $this->assertEquals($groupId, $result->id);
        $this->assertEquals(
            GroupControllerTest::COLUMNS_VISIBILITY,
            $result->columns_visibility['columns_visibility']
        );
    }

    /**
     * @test
     * that getGroupSettings returns null when there is no results with provided group id
     *
     * @covers ::getGroupSettings
     */
    public function getGroupSettings_whenThereIsNoFacebookGroup_returnsNull()
    {
        $groupId = 1;
        $currentMock = $this->createPartialMock(User::class, ['groupSettings']);
        $belongsToManyMock = $this->getMockBuilder(BelongsToMany::class)
            ->disableOriginalConstructor()
            ->addMethods(['where'])
            ->getMock();
        $facebookGroupMock = $this->getMockBuilder(FacebookGroups::class)->addMethods(['first'])->getMock();
        $currentMock->expects(static::once())
            ->method('groupSettings')
            ->willReturn($belongsToManyMock);

        $belongsToManyMock->expects(static::once())
            ->method('where')
            ->with('group_id', $groupId)
            ->willReturn($facebookGroupMock);
        $facebookGroupMock->expects(static::once())->method('first')->willReturn(null);

        $result = $currentMock->getGroupSettings($groupId);

        $this->assertNull($result);
    }

    /**
     * @test
     * that getGroupSettings returns Facebook group instance with the columns visibility and width
     *
     * @covers ::getGroupSettings
     */
    public function getGroupSettings_happyPath_returnsGroupSettings()
    {
        $groupId = 1;
        $currentMock = $this->createPartialMock(User::class, ['groupSettings']);
        $belongsToManyMock = $this->getMockBuilder(BelongsToMany::class)
            ->disableOriginalConstructor()
            ->addMethods(['where'])
            ->getMock();
        $facebookGroupMock = $this->getMockBuilder(FacebookGroups::class)->addMethods(['first'])->getMock();
        $currentMock->expects(static::once())
            ->method('groupSettings')
            ->willReturn($belongsToManyMock);

        $belongsToManyMock->expects(static::once())
            ->method('where')
            ->with('group_id', $groupId)
            ->willReturn($facebookGroupMock);
        $facebookGroupMock->setAttribute('id', $groupId);
        $facebookGroupMock->setAttribute(
            'group_settings',
            [
                'columns_visibility' => json_encode(GroupControllerTest::COLUMNS_VISIBILITY),
                'columns_width' => json_encode(GroupControllerTest::COLUMNS_WIDTH),
            ]
        );
        $facebookGroupMock->expects(static::once())->method('first')->willReturnSelf();

        $result = $currentMock->getGroupSettings($groupId);

        $this->assertInstanceOf(FacebookGroups::class, $result);
        $this->assertEquals($groupId, $result->id);
        $this->assertEquals(
            json_encode(GroupControllerTest::COLUMNS_VISIBILITY),
            $result->group_settings['columns_visibility']
        );
        $this->assertEquals(
            json_encode(GroupControllerTest::COLUMNS_WIDTH),
            $result->group_settings['columns_width']
        );
    }
}
