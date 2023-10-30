<?php

namespace Tests\Unit\App\Http\Controllers\Admin\Api\V1;

use App\FacebookGroups;
use App\GroupMembers;
use App\Http\Controllers\Admin\Api\V1\AdminController;
use App\Http\Controllers\Admin\Traits\AdminControllerBehavior;
use App\Http\Middleware\AdminRequest;
use App\Http\Middleware\ValidateAjaxRequest;
use App\Mail\InviteUser;
use App\Mail\UpdateEmail;
use App\Plan;
use App\Services\SubscriptionService;
use App\User;
use Exception;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

/**
 * Class AdminControllerTest adds test coverage for {@see AdminController}
 *
 * @package Tests\Unit\App\Http\Controllers\Admin\Api\V1
 * @coversDefaultClass \App\Http\Controllers\Admin\Api\V1\AdminController
 */
class AdminControllerTest extends TestCase
{
    use DatabaseMigrations;
    use DatabaseTransactions;
    use AdminControllerBehavior;

    /**
     * Setup test dependencies
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(AdminRequest::class);
    }

    /**
     * @test
     * that init returns validation message according to the provided key and values
     *
     * @covers ::init
     *
     * @dataProvider init_withVariousRequestParamsForCreateUserRouteProvider
     *
     * @param string $requestType of the tested route
     * @param string $uri of the tested route
     * @param array $requestData containing key value pair params
     * @param string $expectedMessage of the tested method call
     */
    public function init_withVariousRequestParamsForCreateUserRoute_returnsValidationMessage(
        string $requestType,
        string $uri,
        array $requestData,
        string $expectedMessage
    ) {
        User::factory(['email' => 'john_doe@gmail.com', 'id' => 1])->create();

        $response = $this->call($requestType, "/api/admin/v1/{$uri}", $requestData);

        $response->assertStatus(Response::HTTP_BAD_REQUEST);
        $response->assertJsonStructure(['message', 'data']);
        $response->assertJsonFragment([
            'message' => $expectedMessage,
            'data'    => [],
        ]);
    }

    /**
     * Data provider for {@see init_withVariousRequestParamsForCreateUserRoute_returnsValidationMessage}
     *
     * @return array[] containing route request type, uri of the route, request data
     * and expected message of the tested method call
     */
    public function init_withVariousRequestParamsForCreateUserRouteProvider(): array
    {
        $existingUserId = 1;

        return [
            # Validation test cases for updateUserStatus method
            'User id is required for updateUserStatus method'                                => [
                'requestType'     => 'PUT',
                'uri'             => 'updateUserStatus',
                'requestData'     => [
                    'user_status' => 1,
                ],
                'expectedMessage' => 'The user id field is required.',
            ],
            'User status is required for updateUserStatus method'                            => [
                'requestType'     => 'PUT',
                'uri'             => 'updateUserStatus',
                'requestData'     => [
                    'user_id' => $existingUserId,
                ],
                'expectedMessage' => 'The user status field is required.',
            ],
            'User id needs to exists in database for updateUserStatus method'                => [
                'requestType'     => 'PUT',
                'uri'             => 'updateUserStatus',
                'requestData'     => [
                    'user_id'     => 5,
                    'user_status' => 0,
                ],
                'expectedMessage' => 'The selected user id is invalid.',
            ],
            'User status needs to be integer for updateUserStatus method'                    => [
                'requestType'     => 'PUT',
                'uri'             => 'updateUserStatus',
                'requestData' => [
                    'user_id'     => $existingUserId,
                    'user_status' => 'dasd',
                ],
                'expectedMessage' => 'The user status must be an integer.  The user status may not be greater than 1.',
            ],
            'User status needs to be integer from 0 to 1 for updateUserStatus method'        => [
                'requestType'     => 'PUT',
                'uri'             => 'updateUserStatus',
                'requestData'     => [
                    'user_id'     => $existingUserId,
                    'user_status' => 2,
                ],
                'expectedMessage' => 'The user status may not be greater than 1.',
            ],
            'User id needs to be integer for updateUserStatus method'                        => [
                'requestType'     => 'PUT',
                'uri'             => 'updateUserStatus',
                'requestData'     => [
                    'user_id'     => 'string',
                    'user_status' => 1,
                ],
                'expectedMessage' => 'The user id must be an integer.',
            ],
            # Validation test cases for removeUser method
            'User id is required for removeUser method'                                      => [
                'requestType'     => 'DELETE',
                'uri'             => 'removeUser',
                'requestData'     => [
                ],
                'expectedMessage' => 'The user id field is required.',
            ],
            'User id needs to be integer for removeUser method'                              => [
                'requestType'     => 'DELETE',
                'uri'             => 'removeUser',
                'requestData'     => [
                    'user_id' => 'two',
                ],
                'expectedMessage' => 'The user id must be an integer.',
            ],
            'User id needs to exists in database for removeUser method'                      => [
                'requestType'     => 'DELETE',
                'uri'             => 'removeUser',
                'requestData'     => [
                    'user_id' => 4,
                ],
                'expectedMessage' => 'The selected user id is invalid.',
            ],
            # Validation test cases for getUserDetails method
            'User id needs to exists in database for getUserDetails method'                  => [
                'requestType'     => 'GET',
                'uri'             => 'getUserDetails',
                'requestData'     => [
                    'user_id' => 4,
                ],
                'expectedMessage' => 'The selected user id is invalid.',
            ],
            'User id needs to be integer for getUserDetails method'                          => [
                'requestType'     => 'GET',
                'uri'             => 'getUserDetails',
                'requestData'     => [
                    'user_id' => ['key' => 'value'],
                ],
                'expectedMessage' => 'The user id must be an integer.',
            ],
            'User id is required for getUserDetails method'                                  => [
                'requestType'     => 'GET',
                'uri'             => 'getUserDetails',
                'requestData'     => [
                ],
                'expectedMessage' => 'The user id field is required.',
            ],
            # Validation test cases for updateUsersPassword method
            'User id is required for updateUsersPassword method'                             => [
                'requestType'     => 'PUT',
                'uri'             => 'updateUsersPassword',
                'requestData'     => [
                    'password' => 'Password123',
                ],
                'expectedMessage' => 'The user id field is required.',
            ],
            'Password is required for updateUsersPassword method'                            => [
                'requestType'     => 'PUT',
                'uri'             => 'updateUsersPassword',
                'requestData'     => [
                    'user_id' => $existingUserId,
                ],
                'expectedMessage' => 'The password field is required.',
            ],
            'Password field needs to be minimum 8 characters for updateUsersPassword method' => [
                'requestType'     => 'PUT',
                'uri'             => 'updateUsersPassword',
                'requestData'     => [
                    'user_id'  => $existingUserId,
                    'password' => 'Passwor',
                ],
                'expectedMessage' => 'The password must be at least 8 characters.',
            ],
            'User id needs to exists in database for updateUsersPassword method'             => [
                'requestType'     => 'PUT',
                'uri'             => 'updateUsersPassword',
                'requestData'     => [
                    'user_id'  => 3,
                    'password' => 'Password123',
                ],
                'expectedMessage' => 'The selected user id is invalid.',
            ],
            # Validation test cases for addTeamMember method
            'Owner id needs to exist in database for addTeamMember method'                  => [
                'requestType'     => 'POST',
                'uri'             => 'addTeamMember',
                'requestData'     => [
                    'owner_id' => 3,
                    'name'     => 'John Doe',
                    'email'    => 'john.doe@gmail.com',
                ],
                'expectedMessage' => 'The selected owner id is invalid.',
            ],
            'Owner id is required for addTeamMember method'                                  => [
                'requestType'     => 'POST',
                'uri'             => 'addTeamMember',
                'requestData'     => [
                    'name'  => 'John Doe',
                    'email' => 'john.doe@gmail.com',
                ],
                'expectedMessage' => 'The owner id field is required.',
            ],
            'Email is required for addTeamMember method'                                     => [
                'requestType'     => 'POST',
                'uri'             => 'addTeamMember',
                'requestData'     => [
                    'owner_id' => $existingUserId,
                    'name'     => 'John Doe',
                ],
                'expectedMessage' => 'The email field is required.',
            ],
            # Validation test cases for createUser method
            'First name is required for createUser method'                                   => [
                'requestType'     => 'POST',
                'uri'             => 'createUser',
                'requestData'     => [
                    'lastName' => 'Doe',
                    'email'    => 'john.doe@gmail.com',
                    'plan'     => Plan::STRIPE_FREE_PLAN_TITLES[0],
                ],
                'expectedMessage' => 'The first name field is required.',
            ],
            'Last name is required for createUser method'                                    => [
                'requestType'     => 'POST',
                'uri'             => 'createUser',
                'requestData'     => [
                    'firstName' => 'John',
                    'email'     => 'john.doe@gmail.com',
                    'plan'      => Plan::STRIPE_FREE_PLAN_TITLES[0],
                ],
                'expectedMessage' => 'The last name field is required.',
            ],
            'Email is required for createUser method'                                        => [
                'requestType'     => 'POST',
                'uri'             => 'createUser',
                'requestData'     => [
                    'firstName' => 'John',
                    'lastName'  => 'Doe',
                    'plan'      => Plan::STRIPE_FREE_PLAN_TITLES[0],
                ],
                'expectedMessage' => 'The email field is required.',
            ],
            'Email fields needs to be valid for createUser method'                           => [
                'requestType'     => 'POST',
                'uri'             => 'createUser',
                'requestData'     => [
                    'firstName' => 'John',
                    'lastName'  => 'Doe',
                    'email'     => 'johndoe',
                    'plan'      => Plan::STRIPE_FREE_PLAN_TITLES[1],
                ],
                'expectedMessage' => 'The email must be a valid email address.',
            ],
            'Email fields needs to be unique for createUser method'                          => [
                'requestType'     => 'POST',
                'uri'             => 'createUser',
                'requestData'     => [
                    'firstName' => 'John',
                    'lastName'  => 'Doe',
                    'email'     => 'john_doe@gmail.com',
                    'plan'      => Plan::STRIPE_FREE_PLAN_TITLES[1],
                ],
                'expectedMessage' => 'The email has already been taken.',
            ],
            'Plan is required for createUser method'                                         => [
                'requestType'     => 'POST',
                'uri'             => 'createUser',
                'requestData'     => [
                    'firstName' => 'John',
                    'lastName'  => 'Doe',
                    'email'     => 'john.doe@gmail.com',
                ],
                'expectedMessage' => 'The plan field is required.',
            ],
            'Plan needs to be in Stripe Free Plans titles for createUser method'             => [
                'requestType'     => 'POST',
                'uri'             => 'createUser',
                'requestData'     => [
                    'firstName' => 'John',
                    'lastName'  => 'Doe',
                    'email'     => 'john.doe@gmail.com',
                    'plan'      => 'PRO_MONTHLY',
                ],
                'expectedMessage' => 'The selected plan is invalid.',
            ],
            # Validation test cases for getSubscriptions method
            'Limit is required field for getSubscriptions method' => [
                'requestType'     => 'GET',
                'uri'             => 'getSubscriptions',
                'requestData'     => [],
                'expectedMessage' => 'The limit field is required.',
            ],
            'Limit needs to be integer for getSubscriptions method' => [
                'requestType'     => 'GET',
                'uri'             => 'getSubscriptions',
                'requestData'     => [
                    'limit' => 'one hundred',
                ],
                'expectedMessage' => 'The limit must be an integer.',
            ],
            'Limit max value is 100 for getSubscriptions method' => [
                'requestType'     => 'GET',
                'uri'             => 'getSubscriptions',
                'requestData'     => [
                    'limit' => 101,
                ],
                'expectedMessage' => 'The limit may not be greater than 100.',
            ],
            'Starting after needs to be string (if it\'s provided) for getSubscriptions method' => [
                'requestType'     => 'GET',
                'uri'             => 'getSubscriptions',
                'requestData'     => [
                    'limit'          => 1,
                    'starting_after' => 101,
                ],
                'expectedMessage' => 'The starting after must be a string.',
            ],
            # Validation test cases for getApproveMembersCount method
            'Users is required field for getApproveMembersCount method' => [
                'requestType'     => 'GET',
                'uri'             => 'getApproveMembersCount',
                'requestData'     => [],
                'expectedMessage' => 'The users field is required.',
            ],
            # Validation test cases for resetMonthlyApproval method
            'User id required for resetMonthlyApproval method' => [
                'requestType' => 'POST',
                'uri' => 'resetMonthlyApproval',
                'requestData' => [],
                'expectedMessage' => 'The user id field is required.',
            ],
            'User id needs to be integer for resetMonthlyApproval method' => [
                'requestType' => 'POST',
                'uri' => 'resetMonthlyApproval',
                'requestData' => [
                    'user_id' => 'string',
                ],
                'expectedMessage' => 'The user id must be an integer.',
            ],
            'User id needs to exists in database for resetMonthlyApproval method' => [
                'requestType' => 'POST',
                'uri' => 'resetMonthlyApproval',
                'requestData' => [
                    'user_id' => 900, // user id which is not exists in users table.
                ],
                'expectedMessage' => 'The selected user id is invalid.',
            ],
            # Validation test cases for sendNewEmailActivationLink method
            'User id & email address required for sendNewEmailActivationLink method'    => [
                'requestType'     => 'PUT',
                'uri'             => 'sendNewEmailActivationLink',
                'requestData'     => [],
                'expectedMessage' => 'The user id field is required.  The email field is required.',
            ],
            'User id required for sendNewEmailActivationLink method'                    => [
                'requestType'     => 'PUT',
                'uri'             => 'sendNewEmailActivationLink',
                'requestData'     => [
                    'email' => 'abc@gmail.com',
                ],
                'expectedMessage' => 'The user id field is required.',
            ],
            'User id needs to be integer for sendNewEmailActivationLink method'         => [
                'requestType'     => 'PUT',
                'uri'             => 'sendNewEmailActivationLink',
                'requestData'     => [
                    'user_id' => 'string',
                    'email'   => 'abc@gmail.com',
                ],
                'expectedMessage' => 'The user id must be an integer.',
            ],
            'User id needs to exists in database for sendNewEmailActivationLink method' => [
                'requestType'     => 'PUT',
                'uri'             => 'sendNewEmailActivationLink',
                'requestData'     => [
                    'user_id' => 900, // user id which is not exists in users table.
                    'email'   => 'abc@gmail.com',
                ],
                'expectedMessage' => 'The selected user id is invalid.',
            ],
            'Email is required for sendNewEmailActivationLink method'                   => [
                'requestType'     => 'PUT',
                'uri'             => 'sendNewEmailActivationLink',
                'requestData'     => [
                    'user_id' => 1,
                ],
                'expectedMessage' => 'The email field is required.',
            ],
            'Email fields needs to be valid for sendNewEmailActivationLink method'      => [
                'requestType'     => 'PUT',
                'uri'             => 'sendNewEmailActivationLink',
                'requestData'     => [
                    'user_id' => 1,
                    'email'   => 'johndoe',
                ],
                'expectedMessage' => 'The email must be a valid email address.',
            ],
            'Email fields needs to be unique for sendNewEmailActivationLink method'     => [
                'requestType'     => 'PUT',
                'uri'             => 'sendNewEmailActivationLink',
                'requestData'     => [
                    'user_id' => 1,
                    'email'   => 'john_doe@gmail.com',
                ],
                'expectedMessage' => 'The email has already been taken.',
            ],
            'The email may not be greater than 191 characters for sendNewEmailActivationLink method' => [
                'requestType'     => 'PUT',
                'uri'             => 'sendNewEmailActivationLink',
                'requestData'     => [
                    'user_id' => 1,
                    'email'   => 'john_doe_randommmmmmmmmmmmmmmmmmmmmmmmmmmmmmmmmm@LonggggggggggggggggggggggggText' .
                        'xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxstringgggggggggggggg' .
                        'ggggggggggggggggggmail.com',
                ],
                'expectedMessage' => 'The email may not be greater than 191 characters.',
            ],
        ];
    }

    /**
     * @test
     * that getUsersList returns JSON with encrypted users including subscription
     *
     * @covers ::getUsersList
     */
    public function getUsersList_happyPath_returnsEncryptedUsers()
    {
        $users = User::factory(5)->create();
        $userMock = $this->mock(User::class);
        $userMock->shouldReceive('getUsersDetails')->andReturn($users);
        $expectedDecodedResponse = ['user' => $users->toArray()];

        $this->app->instance(User::class, $userMock);

        $response = $this->get(route('getUsersList'));

        $response->assertOk();
        $response->assertJsonStructure(['message', 'data']);
        $response->assertJsonFragment(['message' => 'List of users']);
        $this->assertEquals(
            $expectedDecodedResponse,
            json_decode($this->decrypt(json_decode($response->getContent())->data), true)
        );
    }

    /**
     * @test
     * that getUsersList returns {@see Response::HTTP_BAD_REQUEST} response if an exception is thrown
     *
     * @covers ::getUsersList
     */
    public function getUsersList_whenAnExceptionIsThrown_returnsBadHTTPRequest()
    {
        User::factory(5)->create();
        $userMock = $this->mock(User::class);
        $userMock->shouldReceive('getUsersDetails')->andThrow(new Exception());

        $this->app->instance(User::class, $userMock);

        $response = $this->get(route('getUsersList'));
        $response->assertStatus(Response::HTTP_BAD_REQUEST);
        $response->assertJsonStructure(['message']);
        $response->assertJsonFragment(['message' => 'Invalid Request']);
    }

    /**
     * @test
     * that updateUserStatus changes user status according to the provided status
     *
     * @covers ::updateUserStatus
     *
     * @dataProvider updateUserStatus_withVariousStatusesProvider
     *
     * @param int $userStatus represents status of the {@see User}
     * @param int $newStatus represents new status that will be updated for {@see User}
     * @param string $expectedResponseMessage of the tested method call
     */
    public function updateUserStatus_withVariousStatuses_updatesStatus(
        int $userStatus,
        int $newStatus,
        string $expectedResponseMessage
    ) {
        $user = User::factory(['status' => $userStatus])->create();

        $response = $this->put(
            route('updateUserStatus'),
            [
                'user_id'     => $user->id,
                'user_status' => $newStatus,
            ]
        );

        $response->assertOk();
        $response->assertJsonStructure(['message']);
        $response->assertJsonFragment(['message' => $expectedResponseMessage]);
        $this->assertDatabaseHas('users', [
            'id'     => $user->id,
            'name'   => $user->name,
            'status' => $newStatus,
        ]);
    }

    /**
     * Data provider for {@see updateUserStatus_withVariousStatuses_updatesStatus}
     *
     * @return array[] containing user status, new user status and expected response message
     */
    public function updateUserStatus_withVariousStatusesProvider(): array
    {
        return [
            [
                'userStatus'              => 1,
                'newStatus'               => 0,
                'expectedResponseMessage' => 'User deactivated',
            ],
            [
                'userStatus'              => 0,
                'newStatus'               => 1,
                'expectedResponseMessage' => 'User activated',
            ],
        ];
    }

    /**
     * @test
     * that removeUser soft deletes the requested user
     *
     * @covers ::removeUser
     */
    public function removeUser_always_softDeletesUser()
    {
        $user = User::factory(['deleted_at' => null])->create();

        $response = $this->delete(route('removeUser', ['user_id' => $user->id]));

        $response->assertOk();
        $response->assertJsonStructure(['message']);
        $response->assertJsonFragment(['message' => 'User deleted successfully']);
        $this->assertSoftDeleted('users', [
            'id' => $user->id,
            'name' => $user->name,
        ]);
    }

    /**
     * @test
     * that getUserDetails returns user data in response without subscription details
     *
     * @covers ::getUserDetails
     */
    public function getUserDetails_withoutActivePlanDetails_returnsUserData()
    {
        $user = User::factory()->create();
        $facebookGroup = FacebookGroups::factory(['user_id' => $user->id])->create();
        $groupMembers = GroupMembers::factory(10)->create([
            'user_id'     => $user->id,
            'group_id'    => $facebookGroup->id,
            'is_approved' => true,
        ]);

        $response = $this->get(route('getUserDetails', ['user_id' => $user->id]));

        $response->assertOk();
        $response->assertJsonStructure(['message', 'data']);
        $response->assertJsonFragment(['message' => 'User details.']);
        $responseData = json_decode($this->decrypt(json_decode($response->getContent())->data));
        $this->assertEquals($user->id, $responseData->user->id);
        $this->assertEquals($user->name, $responseData->user->name);
        $this->assertEquals($facebookGroup->id, $responseData->groups[0]->id);
        $this->assertEquals($groupMembers->count(), $responseData->groups[0]->totalGroupMembersApprovals);
        $this->assertNull($responseData->planDetails);
    }

    /**
     * @test
     * that getUserDetails returns user data with plan details when user has active plan
     *
     * @covers ::getUserDetails
     */
    public function getUserDetails_withActivePlanDetails_returnsActivePlanInfo()
    {
        $user = User::factory()->create(['stripe_id' => 'cus_312312das23']);
        $facebookGroup = FacebookGroups::factory(['user_id' => $user->id])->create();
        GroupMembers::factory(10)->create([
            'user_id'     => $user->id,
            'group_id'    => $facebookGroup->id,
            'is_approved' => true,
        ]);

        $subscription = (object)[
            'stripe_id'            => '',
            'stripe_status'        => 'trialing',
            'stripe_plan'          => Plan::STRIPE_PLAN_IDS['default']['BASIC'],
            'name'                 => '',
            'current_period_start' => '',
            'current_period_end'   => '',
            'ends_at'              => '',
            'trial_ends_at'        => '',
            'quantity'             => '1',
        ];
        $this->partialMock(User::class)->expects('getSubscriptionDetails')->with($user->stripe_id)
            ->andReturn($subscription);

        $response = $this->get(route('getUserDetails', ['user_id' => $user->id]));

        $responseData = json_decode($this->decrypt(json_decode($response->getContent())->data));
        $this->assertNotNull($responseData->groupLimit);
        $this->assertNotNull($responseData->membersLimit);
        $this->assertEquals($user->name, $responseData->user->name);
        $this->assertEquals(1, $responseData->planDetails->quantity);
        $this->assertEquals('Trial', $responseData->planDetails->stripe_status);
        $response->assertOk();
        $response->assertJsonStructure(['message', 'data']);
    }

    /**
     * @test
     * that updateUsersPassword updates password for the requested user
     *
     * @covers ::updateUsersPassword
     */
    public function updateUsersPassword_always_setsUserNewPassword()
    {
        $user = User::factory(['password' => '123456789'])->create();
        $newPassword = 'password321';

        $response = $this->put(
            route('updateUsersPassword'),
            [
                'user_id'  => $user->id,
                'password' => $newPassword,
            ]
        );

        $response->assertOk();
        $response->assertJsonStructure(['message']);
        $response->assertJsonFragment(['message' => 'User Details Updated Successfully.']);
        $this->assertTrue(Hash::check($newPassword, User::find($user->id)->password));
    }

    /**
     * @test
     * that addTeamMember returns {@see Response::HTTP_INTERNAL_SERVER_ERROR}
     * if user has reached the limit for adding new members
     *
     * @covers ::addTeamMember
     */
    public function addTeamMember_whenCantAddTeamMembers_returnsHTTPInternalServerErrorResponse()
    {
        $owner = User::factory()->create();
        $teamMember = [
            'owner_id' => $owner->id,
            'name'     => 'John Doe',
            'email'    => 'john.doe@gmail.com',
        ];
        $userMock = $this->mock(User::class);
        $userMock->shouldReceive('find')->with($owner->id)->andReturnSelf();
        $userMock->shouldReceive('canAddTeamMembers')->andReturn(false);

        $this->app->instance(User::class, $userMock);

        $response = $this->post(route('addTeamMember', $teamMember));

        $response->assertJsonStructure(['message']);
        $response->assertJsonFragment([
            'message' => 'The owner has reached the limit of the adding new team members',
        ]);
        $response->assertStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
    }

    /**
     * @test
     * that addTeamMember returns appropriate HTTP response according to the team member creation
     *
     * @covers ::addTeamMember
     *
     * @dataProvider addTeamMember_withVariousTeamMemberStatesProvider
     *
     * @param bool $teamMemberIsCreated indicator of the team member creation
     * @param string $expectedMessage of the tested method call
     * @param int $expectedStatus of the tested method call
     */
    public function addTeamMember_withVariousTeamMemberStates_returnsAppropriateHTTPResponse(
        bool $teamMemberIsCreated,
        string $expectedMessage,
        int $expectedStatus
    ) {
        $owner = User::factory()->create();

        $teamMember = [
            'owner_id' => $owner->id,
            'name'     => 'John Doe',
            'email'    => 'john.doe@gmail.com',
        ];
        $userMock = $this->mock(User::class);
        $userMock->shouldReceive('find')->with($owner->id)->andReturnSelf();
        $userMock->shouldReceive('canAddTeamMembers')->andReturn(true);
        $userMock->shouldReceive('addTeamMember')
            ->with(
                $owner->id,
                Arr::only($teamMember, ['name', 'email'])
            )
            ->andReturn([
                'message' => $expectedMessage,
                'success' => $teamMemberIsCreated,
            ]);
        $this->app->instance(User::class, $userMock);
        $response = $this->post(route('addTeamMember', $teamMember));

        $response->assertStatus($expectedStatus);
        $response->assertJsonStructure(['message']);
        $response->assertJsonFragment(['message' => $expectedMessage]);
    }

    /**
     * Data provider for {@see addTeamMember_withVariousTeamMemberStates_returnsAppropriateHTTPResponse}
     *
     * @return array[] containing indicator for team member creation,
     * expected status and message of the tested method call
     */
    public function addTeamMember_withVariousTeamMemberStatesProvider(): array
    {
        return [
            'Successfully created team member' => [
                'teamMemberIsCreated' => true,
                'expectedMessage' => 'Invite sent to team member successfully.',
                'expectedStatus'  => Response::HTTP_OK,
            ],
            'Team Member is not created' => [
                'teamMemberIsCreated' => false,
                'expectedMessage' => 'Unable To Add Team Member.',
                'expectedStatus'  => Response::HTTP_BAD_REQUEST,
            ],
        ];
    }

    /**
     * @test
     * that createUser sends invite email to user and stores the user in the database
     *
     * @covers ::createUser
     */
    public function createUser_happyPath_storeUser()
    {
        Mail::fake();
        $user = [
            'email'     => 'john.doe@gmail.com',
            'firstName' => 'John',
            'lastName'  => 'Doe',
            'plan'      => 'FREE_PRO',
        ];

        $response = $this->json('POST', 'api/admin/v1/createUser', $user);

        $userCreatedModel = User::where('email', $user['email'])->first();

        $subscriptionServiceMock = $this->mock(SubscriptionService::class);
        $subscriptionServiceMock->shouldReceive('createCustomer')
            ->with($userCreatedModel)
            ->andReturn($userCreatedModel);
        $subscriptionServiceMock->shouldReceive('subscription')
            ->with($userCreatedModel, Plan::STRIPE_PLAN_IDS['default'][$user['plan']]);

        Mail::assertSent(InviteUser::class);
        $response->assertOk();
        $response->assertJsonStructure(['message']);
        $response->assertJsonFragment(['message' => 'Successfully created new user']);

        $this->assertDatabaseHas('users', [
            'name' => $user['firstName'] . ' ' . $user['lastName'],
            'email' => $user['email'],
        ]);
    }

    /**
     * @test
     * that createUser returns {@see Response::HTTP_INTERNAL_SERVER_ERROR} if an exception is thrown
     *
     * @covers ::createUser
     */
    public function createUser_whenAnExceptionIsThrown_returnsHTTPInternalServerErrorResponse()
    {
        $this->withoutMiddleware(ValidateAjaxRequest::class);
        $user = [
            'email'     => 'john.doe@gmail.com',
            'firstName' => 'John',
            'lastName'  => 'Doe',
            'plan'      => [],
        ];

        $response = $this->json('POST', 'api/admin/v1/createUser', $user);

        $response->assertStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
        $response->assertJsonStructure(['message']);
        $response->assertJsonFragment(['message' => 'Server error']);

        $this->assertDatabaseMissing('users', [
            'name' => $user['firstName'] . ' ' . $user['lastName'],
            'email' => $user['email'],
        ]);
    }

    /**
     * @test
     * that getSubscriptions returns appropriate HTTP response according to the success of getting subscription list
     *
     * @covers ::getSubscriptions
     *
     * @dataProvider getSubscriptions_withVariousResponsesProvider
     *
     * @param bool $getSubscriptionSuccess indicator
     * @param array|null $subscriptionData represents the Stripe subscription response data
     * @param string $expectedMessage of the tested method call
     */
    public function getSubscriptions_withVariousResponses_returnsResponse(
        bool $getSubscriptionSuccess,
        ?array $subscriptionData,
        string $expectedMessage
    ) {
        $subscriptionServiceMock = $this->mock(SubscriptionService::class);
        $subscriptionServiceMock->shouldReceive('subscriptionList')->with(10, null)->andReturn([
            'message'       => $expectedMessage,
            'subscriptions' => $subscriptionData,
            'success'       => $getSubscriptionSuccess,
        ]);

        $response = $this->get(route('getSubscriptions', ['limit' => 10]));

        $response->assertOk();
        $response->assertJsonFragment(['message' => $expectedMessage]);
        if ($getSubscriptionSuccess) {
            $response->assertJsonStructure(['message', 'data']);
            $responseData = json_decode($this->decrypt(json_decode($response->getContent())->data), true);
            $this->assertEquals($subscriptionData, $responseData['subscriptionsList']);
        } else {
            $response->assertJsonStructure(['message']);
        }
    }

    /**
     * Data provider for {@see getSubscriptions_withVariousResponses_returnsResponse}
     *
     * @return array[] containing get subscription success indicator, stubbed subscription data
     * and expected message of the tested message call
     */
    public function getSubscriptions_withVariousResponsesProvider(): array
    {
        return [
            [
                'getSubscriptionSuccess' => true,
                'subscriptionData'       => ['JohnDoe' => 'cus_31d12u2asd213'],
                'expectedMessage'        => 'List of subscriptions.',
            ],
            [
                'getSubscriptionSuccess' => false,
                'subscriptionData'       => null,
                'expectedMessage'        => 'Something went wrong',
            ],
        ];
    }

    /**
     * @test
     * that getApproveMembersCount returns total approved members count of the user's approved, group members
     *
     * @covers ::getApproveMembersCount
     */
    public function getApproveMembersCount_always_returnsTotalApprovedMembersCount()
    {
        $users = User::factory(5)->create();

        $createdMembersCount = $users->map(function ($user) {
            $facebookGroup = FacebookGroups::factory(['user_id' => $user->id])->create();

            GroupMembers::factory()->create([
                'user_id'       => $user->id,
                'group_id'      => $facebookGroup->id,
                'date_add_time' => now()->subDays(rand(13, 50))->format('Y-m-d H:i:s'),
                'is_approved'   => true,
            ]);

            $members = GroupMembers::factory(rand(1, 10))->create([
                'user_id'       => $user->id,
                'group_id'      => $facebookGroup->id,
                'date_add_time' => now()->subDays(5)->format('Y-m-d H:i:s'),
                'is_approved'   => true,
            ]);

            return $members->count();
        })->toArray();

        $requestedUsers = $users->map(function ($user) {
            return [
                'user_id'      => $user->id,
                'period_start' => now()->subDays(12)->format('Y-m-d H:i:s'),
                'period_end'   => now()->subDays(2)->format('Y-m-d H:i:s'),
            ];
        })->toArray();

        $response = $this->get(route('getApproveMembersCount', ['users' => $requestedUsers]));

        $response->assertOk();
        $response->assertJsonStructure(['message', 'data']);
        $response->assertJsonFragment(['message' => 'List of members\' counts']);

        $responseData = json_decode($this->decrypt(json_decode($response->getContent())->data), true);
        for ($i = 0; $i < count($responseData['member_count']); $i++) {
            $this->assertEquals($createdMembersCount[$i], $responseData['member_count'][$i]['count']);
        }
    }

    /**
     * @test
     * that resetMonthlyApproval returns {@see Response::HTTP_OK} status,
     * and successful message in response when admin is passing correct user id.
     *
     * @covers ::resetMonthlyApproval
     */
    public function resetMonthlyApproval_withPassingCorrectUserId_returnsSuccessfulResponse()
    {
        $user = $this->actingAsUser();
        $requestParams = ['user_id' => $user->id];

        $response = $this->post(route('resetMonthlyApproval', $requestParams));

        $response->assertJsonStructure(['message']);
        $response->assertOk();
        $response->assertJsonFragment(['message' => __('Monthly approvals limit reset successfully.')]);
    }

    /**
     * @test
     * that send Activation Link to the newly requested email address.
     *
     * @covers ::sendNewEmailActivationLink
     */
    public function sendNewEmailActivationLink_sendsActivationLinkInEmail_returnsMessage()
    {
        Mail::fake();
        $user = User::factory(['email' => 'jennySmith@gmail.com'])->create();
        $emailToUpdate = 'newEmail@gmail.com';

        $response = $this->put(
            route('sendNewEmailActivationLink'),
            [
                'user_id' => $user->id,
                'email'   => $emailToUpdate,
            ]
        );

        $response->assertOk();
        $response->assertJsonStructure(['message']);
        $response->assertJsonFragment([
            'message' => "Confirmation email has been sent to your new email address {$emailToUpdate}"
        ]);

        $this->assertDatabaseHas('users', [
            'id'    => $user->id,
            'email' => $user->email,
        ]);
        $this->assertDatabaseHas('email_update_requests', [
            'current_email' => $user->email,
            'new_email' => $emailToUpdate,
        ]);

        Mail::assertSent(UpdateEmail::class);
    }

    /**
     * @test
     * that sendNewEmailActivationLink returns response message when user is passing already
     * registered users email address.
     *
     * @covers ::sendNewEmailActivationLink
     */
    public function sendNewEmailActivationLink_withAlreadyRequestedEmailAddress_returnResponse()
    {
        Mail::fake();
        User::factory(['email' => 'adomsmith@gmail.com', 'timezone' => 'Africa/Accra'])->create();

        $user = User::factory(['email' => 'jennySmith@gmail.com'])->create();
        $emailToUpdate = 'adomsmith@gmail.com';

        $response = $this->put(
            route('sendNewEmailActivationLink'),
            [
                'user_id' => $user->id,
                'email' => $emailToUpdate,
            ]
        );

        Mail::assertNotSent(UpdateEmail::class);

        $response->assertStatus(Response::HTTP_BAD_REQUEST);
        $response->assertJsonStructure(['message']);
        $response->assertJsonFragment([
            'message' => 'The email has already been taken.',
        ]);

        $this->assertDatabaseMissing('email_update_requests', [
            'current_email' => 'jennySmith@gmail.com',
            'new_email' => $emailToUpdate,
        ]);
    }
}
