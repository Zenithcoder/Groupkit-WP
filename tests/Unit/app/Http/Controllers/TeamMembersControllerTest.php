<?php

namespace Tests\Unit\app\Http\Controllers;

use App\FacebookGroups;
use App\Http\Controllers\TeamMembersController;
use App\Http\Middleware\TeamMember;
use App\Mail\TeamMemberMail;
use App\OwnerToTeamMember;
use App\TeamMemberGroupAccess;
use App\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Mail;
use ReflectionException;
use Stripe\Exception\ApiErrorException;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;
use Tests\TestHelper;

/**
 * Class TeamMembersControllerTest adds test coverage for {@see \App\Http\Controllers\TeamMembersController} class
 *
 * @package Tests\Unit\app\Http\Controllers
 * @coversDefaultClass \App\Http\Controllers\TeamMembersController
 */
class TeamMembersControllerTest extends TestCase
{
    use DatabaseTransactions;
    use DatabaseMigrations;

     /**
     * Setup test dependencies
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(TeamMember::class);
        $this->assertGuest();
    }

    /**
     * @test
     * that init protects all methods from guest users
     *
     * @covers ::init
     *
     * @dataProvider init_withVariousRoutesProvider
     *
     * @param string $requestType of the tested route
     * @param string $url of the tested route
     */
    public function init_withVariousRoutes_redirectsToLogin(string $requestType, string $url)
    {
        $response = $this->call($requestType, $url);

        $this->assertGuest();
        $this->assertEquals(Response::HTTP_FOUND, $response->getStatusCode());
        $response->assertRedirect('login');
        $response->assertSee('login');
    }

    /**
     * Data provider for {@see init_withVariousRoutes_redirectsToLogin}
     *
     * @return array[] containing request type and url for the tested route
     */
    public function init_withVariousRoutesProvider(): array
    {
        return [
            ['requestType' => 'GET', 'url' => 'teamMembers/getData'],
            ['requestType' => 'GET', 'url' => 'teamMembers/getTeamMember/1'],
            ['requestType' => 'GET', 'url' => 'teamMembers'],
            ['requestType' => 'POST', 'url' => 'teamMembers'],
            ['requestType' => 'PUT', 'url' => 'teamMembers/1'],
            ['requestType' => 'POST', 'url' => 'teamMembers/remove'],
        ];
    }

    /**
     * @test
     * that init returns appropriate validation message for every validation rule
     *
     * @covers ::init
     *
     * @dataProvider init_withVariousRequestParamsProvider
     *
     * @param string $requestType of the tested route
     * @param string $uri of the tested route
     * @param array $requestData represents parameters fro the tested route
     * @param string $expectedMessage of the tested route call
     */
    public function init_withVariousRequestParams_returnsValidationMessage(
        string $requestType,
        string $uri,
        array $requestData,
        string $expectedMessage
    ) {
        $this->actingAsUser();

        $response = $this->call($requestType, "/teamMembers{$uri}", $requestData);

        $this->assertAuthenticated();

        $response->assertStatus(Response::HTTP_BAD_REQUEST);
        $response->assertJsonStructure(['message', 'data']);
        $response->assertJsonFragment(['message' => $expectedMessage]);
        $response->assertJsonFragment(['data' => []]);
    }

    /**
     * Data provider for {@see init_withVariousRequestParams_returnsValidationMessage}
     *
     * @return array[] array containing request type, URL, data for the request, and expected validation message
     */
    public function init_withVariousRequestParamsProvider(): array
    {
        return [
            'Name required for store method' => [
                'requestType'   => 'POST',
                'uri'           => '',
                'requestData'   => [
                    'email' => 'john.doe@gmail.com',
                ],
                'expectedMessage' => 'The name field is required.',
            ],
            'Email required for store method' => [
                'requestType'   => 'POST',
                'uri'           => '',
                'requestData'   => [
                    'name' => 'John Doe',
                ],
                'expectedMessage' => 'The email field is required.',
            ],
            'Id required for destroyTeamMembers method' => [
                'requestType'   => 'POST',
                'uri'           => '/remove',
                'requestData'   => [],
                'expectedMessage' => 'The id field is required.',
            ],
            'Email required for checkTeamMembersEmail method' => [
                'requestType'   => 'POST',
                'uri'           => '/checkTeamMembersEmail',
                'requestData'   => [],
                'expectedMessage' => 'The email field is required.',
            ],
            'Email required for getEmail method' => [
                'requestType'   => 'POST',
                'uri'           => '/getEmail',
                'requestData'   => [],
                'expectedMessage' => 'The search field is required.',
            ],
            'Name must be without special characters for store method' => [
                'requestType'   => 'POST',
                'uri'           => '',
                'requestData'   => [
                    'name' => 'John#Doe',
                    'email' => 'john.doe@gmail.com',
                ],
                'expectedMessage' => 'The name format is invalid.',
            ],
            'Email must be validated for store method' => [
                'requestType'   => 'POST',
                'uri'           => '',
                'requestData'   => [
                    'name' => 'John Doe',
                    'email' => 'john.doe',
                ],
                'expectedMessage' => 'The email must be a valid email address.',
            ],
            'Email must be validated for checkTeamMembersEmail method' => [
                'requestType'   => 'POST',
                'uri'           => '/checkTeamMembersEmail',
                'requestData'   => [
                    'email' => 'john.doe',
                ],
                'expectedMessage' => 'The email must be a valid email address.',
            ],
            'Email must be validated for getEmail method' => [
                'requestType'   => 'POST',
                'uri'           => '/checkTeamMembersEmail',
                'requestData'   => [
                    'email' => 'john',
                ],
                'expectedMessage' => 'The email must be a valid email address.',
            ],
        ];
    }

    /**
     * @test
     * that team members returns team members view
     *
     * @covers ::teamMembers
     */
    public function teamMembers_always_returnsTeamMembersView()
    {
        $owner = $this->actingAsUser();

        $userMock = $this->getMockBuilder(User::class)
            ->onlyMethods(['canAddTeamMembers'])
            ->setProxyTarget($owner)
            ->disableOriginalConstructor()
            ->getMock();
        $userMock->setAttribute('id', $owner->id);
        $this->actingAs($userMock);
        $userMock->expects(static::once())->method('canAddTeamMembers')->willReturn(true);

        $this->app->instance('User', $userMock);

        $response = $this->get(route('teamMembers'));

        $this->assertAuthenticated();
        $response->assertStatus(Response::HTTP_OK);
        $response->assertViewIs('teammembers');
    }

    /**
     * @test
     * that getTeamMember returns HTTP Unauthorized code if the user is unauthorized to get team member data
     *
     * @covers ::getTeamMember
     */
    public function getTeamMember_whenUserIsUnAuthorized_returnsHTTPUnAuthorized()
    {
        $this->actingAsUser();

        $response = $this->json('GET', route('getTeamMember', 55));

        $this->assertAuthenticated();
        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonStructure(['code', 'message', 'data']);
        $response->assertJsonFragment(['code' => Response::HTTP_UNAUTHORIZED]);
        $response->assertJsonFragment(['message' => 'Unauthorized']);
        $response->assertJsonFragment(['data' => '']);
    }

    /**
     * @test
     * that getTeamMember returns team members group ids if the owner has access to the team member
     *
     * @covers ::getTeamMember
     */
    public function getTeamMember_whenUserHasAccess_returnsTeamMembersGroupIds()
    {
        $owner = $this->actingAsUser();
        $teamMembers = $this->createTeamMembersSetUp($owner);

        $teamMember = User::with('teamMemberGroupAccess')->find($teamMembers[0]->id);

        $response = $this->json('GET', route('getTeamMember', $teamMember->id));

        $this->assertAuthenticated();
        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonStructure(['code', 'message', 'user', 'fb_id']);
        $response->assertJsonFragment(['code' => Response::HTTP_OK]);
        $response->assertJsonFragment(['message' => 'Successfully.']);
        $response->assertJsonFragment(['fb_id' => $teamMember->teamMemberGroupAccess->pluck('id')]);
    }

    /**
     * @test
     * that getData returns all members of the owner team without filtering by the specific field
     *
     * @covers ::getData
     */
    public function getData_happyPath_returnsTeamMembers()
    {
        $owner = $this->actingAsUser();
        $teamMembers = $this->createTeamMembersSetUp($owner);

        $response = $this->json('GET', route('getData'));

        foreach ($teamMembers as $teamMember) {
            $response->assertJsonFragment([
                'name'   => $teamMember->name,
                'id'     => $teamMember->id,
                'status' => $teamMember->status === 0 ? 'Inactive' : 'Active',
            ]);
        }
    }

    /**
     * @test
     * that getData returns team members filtered by the provided field $filteredBy
     *
     * @covers ::getData
     *
     * @dataProvider getData_filteredByVariousFieldsProvider
     *
     * @param string $filteredBy specified field provided in the request
     * @param int $selectTeamMember from the owner list of team members
     */
    public function getData_filteredByVariousFields_returnsFilteredTeamMembers(
        string $filteredBy,
        int $selectTeamMember
    ) {
        $owner = $this->actingAsUser();
        $teamMembers = $this->createTeamMembersSetUp($owner);

        $requestedTeamMembers = $teamMembers[$selectTeamMember];

        $response = $this->json('GET', route('getData', [$filteredBy => $requestedTeamMembers->{$filteredBy}]));

        $response->assertJsonFragment([
            'name' => $requestedTeamMembers->name,
            'id'   => $requestedTeamMembers->id,
        ]);

        foreach ($teamMembers as $teamMember) {
            if ($teamMember->id !== $requestedTeamMembers->id) {
                $response->assertJsonMissing([
                    'name' => $teamMember->name,
                    'id'   => $teamMember->id,
                ]);
            }
        }
    }

    /**
     * Data provider for {@see getData_filteredByVariousFields_returnsFilteredTeamMembers}
     *
     * @return array[] containing specified filtered by property and selected team member
     */
    public function getData_filteredByVariousFieldsProvider(): array
    {
        return [
            [
                'filteredBy'       => 'email',
                'selectTeamMember' => 0,
            ],
            [
                'filteredBy'       => 'name',
                'selectTeamMember' => 1,
            ],
        ];
    }

    /**
     * @test
     * that store return the error JSON response if the owner can't add a new team member
     *
     * @covers ::store
     *
     * @throws ReflectionException if currentUser property doesn't exists
     * @throws ApiErrorException if the {@see \Stripe\ApiRequestor::request} fails
     */
    public function store_ifOwnerCannotAddTeamMember_returnsErrorResponse()
    {
        $currentMock = $this->getMockBuilder(TeamMembersController::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();

        $userMock = $this->createMock(User::class);
        $userMock->expects(static::once())->method('canAddTeamMembers')->willReturn(false);
        TestHelper::setNonPublicProperty($currentMock, 'currentUser', $userMock);

        $expectedResult = response([
            'message' => 'You have reached the limit of the adding new team members',
            'data'    => ['hide_create_button' => true],
        ], Response::HTTP_INTERNAL_SERVER_ERROR);

        $this->assertEquals($expectedResult, $currentMock->store());
    }

    /**
     * @test
     * that store returns error response when team member already exists in owner's team
     *
     * @covers ::store
     */
    public function store_ifTeamMemberAlreadyExistsInOwnerTeam_returnsErrorResponse()
    {
        $owner = $this->actingAsUser();
        $teamMembers = $this->createTeamMembersSetUp($owner);
        $facebookGroup = FacebookGroups::factory()->create(['user_id' => $owner->id]);

        $userMock = $this->getMockBuilder(User::class)
            ->onlyMethods(['canAddTeamMembers'])
            ->setProxyTarget($owner)
            ->disableOriginalConstructor()
            ->getMock();

        $this->actingAs($userMock);
        $userMock->expects(static::exactly(2))->method('canAddTeamMembers')->willReturnOnConsecutiveCalls(true, true);
        $userMock->setAttribute('id', $owner->id);

        $this->app->instance('User', $userMock);

        $requestData = [
            'name'  => 'John Doe',
            'email' => $teamMembers[0]->email,
            'facebook_groups_id' => $facebookGroup->pluck('id')->toArray(),
        ];

        $response = $this->json('POST', '/teamMembers', $requestData);

        $this->assertEquals(Response::HTTP_BAD_REQUEST, $response->status());
        $response->assertJsonStructure(['message', 'data']);
        $response->assertJsonFragment(['message' => 'The member already exists in your team.']);
        $response->assertJsonFragment(['hide_create_button' => false]);
    }

    /**
     * @test
     * that store:
     * 1. Adds new team member in logged-in owner team
     * 2. Sent an email to the team member
     * 3. Returns success response containing hide_create_button state
     *
     * @covers ::store
     *
     * @dataProvider store_withVariousCanAddTeamMembersStateProvider
     *
     * @param bool $canAddTeamMembers determine hide_create_button behaviour in the response
     */
    public function store_withVariousCanAddTeamMembersState_returnsSuccessResponse(bool $canAddTeamMembers)
    {
        $owner = $this->actingAsUser();
        $facebookGroups = FacebookGroups::factory(3)->create(['user_id' => $owner->id]);

        Mail::fake();
        $userMock = $this->getMockBuilder(User::class)
            ->onlyMethods(['canAddTeamMembers'])
            ->setProxyTarget($owner)
            ->disableOriginalConstructor()
            ->getMock();

        $this->actingAs($userMock);
        $userMock->expects(static::exactly(2))->method('canAddTeamMembers')->willReturn(true, $canAddTeamMembers);
        $userMock->setAttribute('id', $owner->id);

        $this->app->instance('User', $userMock);
        $newTeamMember = User::factory()->make(['name' => 'John Doe']);

        $this->assertDatabaseMissing(
            'users',
            ['email' => $newTeamMember->email, 'name' => $newTeamMember->name]
        );

        $response = $this->json('POST', '/teamMembers', [
            'name'               => $newTeamMember->name,
            'email'              => $newTeamMember->email,
            'facebook_groups_id' => $facebookGroups->pluck('id'),
        ]);

        $newTeamMember = User::where('name', $newTeamMember->name)
            ->where('email', $newTeamMember->email)
            ->first();

        Mail::assertSent(TeamMemberMail::class);
        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonStructure(['message', 'data']);
        $response->assertJsonFragment([
            'message' => 'Invite sent to team member successfully.',
            'data' => ['hide_create_button' => !$canAddTeamMembers],
        ]);
        $this->assertDatabaseHas('users', ['id' => $newTeamMember->id]);
        $this->assertDatabaseHas(
            'owner_to_team_members',
            ['owner_id' => $owner->id, 'team_member_id' => $newTeamMember->id]
        );

        foreach ($facebookGroups->pluck('id') as $facebookGroupId) {
            $this->assertDatabaseHas(
                'team_member_group_access',
                ['user_id' => $newTeamMember->id, 'facebook_group_id' => $facebookGroupId]
            );
        }
    }

    /**
     * Data provider for {@see store_withVariousCanAddTeamMembersState_returnsSuccessResponse}
     *
     * @return array[] containing can add team members
     */
    public function store_withVariousCanAddTeamMembersStateProvider(): array
    {
        return [
            ['canAddTeamMembers' => true],
            ['canAddTeamMembers' => false],
        ];
    }

    /**
     * @test
     * that update returns error response after determining that requested team member is not part of owner's team
     *
     * @covers ::update
     */
    public function update_whenTeamMemberIsNotInOwnerTeam_returnsErrorResponse()
    {
        $owner = $this->actingAsUser();

        $facebookGroups = FacebookGroups::factory(3)->create(['user_id' => $owner->id]);
        $teamMember = User::factory()->create();

        $response = $this->json('PUT', route('teamMembers.update', $teamMember->id), [
            'facebook_groups_id' => $facebookGroups->pluck('id'),
        ]);

        $response->assertStatus(Response::HTTP_UNAUTHORIZED);
        $response->assertJsonStructure(['status', 'message', 'data']);
        $response->assertJsonFragment(['status' => 'error']);
        $response->assertJsonFragment(['message' => 'This user is not currently part of your team.']);
        $response->assertJsonFragment(['data' => []]);
        $this->assertDatabaseMissing('owner_to_team_members', [
            'owner_id' => $owner->id,
            'team_member_id' => $teamMember->id,
        ]);
    }

    /**
     * @test
     * that update changes team member groups access if the team member is part of the owner team
     *
     * @covers ::update
     */
    public function update_whenTeamMemberIsPartOfOwnerTeam_updatesTeamMemberGroupAccess()
    {
        $owner = $this->actingAsUser();
        $facebookGroups = FacebookGroups::factory(3)->create(['user_id' => $owner->id]);

        $teamMember = User::factory()->create();
        $ownerToTeamMembersData = [
            'team_member_id' => $teamMember->id,
            'owner_id'       => $owner->id,
        ];

        $ownerToTeamMember = OwnerToTeamMember::create($ownerToTeamMembersData);
        TeamMemberGroupAccess::insert([
            'user_id' => $teamMember->id,
            'facebook_group_id' => $facebookGroups[0]->id,
            'owner_to_team_member_id' => $ownerToTeamMember->id,
        ]);


        $userMock = $this->getMockBuilder(User::class)
            ->onlyMethods(['canAddTeamMembers'])
            ->setProxyTarget($owner)
            ->disableOriginalConstructor()
            ->getMock();

        $this->actingAs($userMock);

        $canAddTeamMembers = true;
        $userMock->expects(static::once())->method('canAddTeamMembers')->willReturn($canAddTeamMembers);
        $userMock->setAttribute('id', $owner->id);

        $this->app->instance('User', $userMock);

        $response = $this->json('PUT', route('teamMembers.update', $teamMember->id), [
            'facebook_groups_id' => $facebookGroups->where('id', '!=', $facebookGroups[0]->id)->pluck('id'),
        ]);

        $this->assertDatabaseMissing('team_member_group_access', [
            'user_id' => $teamMember->id,
            'facebook_group_id' => $facebookGroups[0]->id,
            'owner_to_team_member_id' => $ownerToTeamMember->id,
        ]);

        $facebookGroups->where('id', '!=', $facebookGroups[0]->id)
            ->pluck('id')
            ->each(function ($facebookGroupId) use ($ownerToTeamMember, $teamMember) {
                $this->assertDatabaseHas('team_member_group_access', [
                    'user_id'                 => $teamMember->id,
                    'facebook_group_id'       => $facebookGroupId,
                    'owner_to_team_member_id' => $ownerToTeamMember->id,
                ]);
            });

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonStructure(['status', 'message', 'data']);
        $response->assertJsonFragment(['status' => 'success']);
        $response->assertJsonFragment(['message' => 'Team Member Details Updated Successfully.']);
        $response->assertJsonFragment(['data' => ['hide_create_button' => !$canAddTeamMembers]]);
    }

    /**
     * @test
     * that destroyTeamMembers returns error response when exception is thrown
     *
     * @covers ::destroyTeamMembers
     */
    public function destroyTeamMembers_whenExceptionIsThrown_returnsErrorResponse()
    {
        $this->actingAsUser();

        $response = $this->json('POST', route('remove', ['id' => 1]));

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonStructure(['code', 'message', 'data']);
        $response->assertJsonFragment(['code' => Response::HTTP_BAD_REQUEST]);
        $response->assertJsonFragment(['message' => "Trying to get property 'id' of non-object"]);
        $response->assertJsonFragment(['data' => '']);
    }

    /**
     * @test
     * that destroyTeamMembers returns success response containing hide_create_button property
     * if is successfully deleted team member
     *
     * @covers ::destroyTeamMembers
     */
    public function destroyTeamMembers_whenTeamMemberCanBeDeleted_returnsSuccessResponse()
    {
        $owner = $this->actingAsUser();
        $facebookGroup = FacebookGroups::factory()->create(['user_id' => $owner->id]);

        $teamMember = User::factory()->create();
        $ownerToTeamMembersData = [
            'team_member_id' => $teamMember->id,
            'owner_id'       => $owner->id,
        ];

        $ownerToTeamMember = OwnerToTeamMember::create($ownerToTeamMembersData);
        TeamMemberGroupAccess::insert([
            'user_id' => $teamMember->id,
            'facebook_group_id' => $facebookGroup->id,
            'owner_to_team_member_id' => $ownerToTeamMember->id,
        ]);


        $userMock = $this->getMockBuilder(User::class)
            ->onlyMethods(['canAddTeamMembers'])
            ->setProxyTarget($owner)
            ->disableOriginalConstructor()
            ->getMock();

        $this->actingAs($userMock);

        $canAddTeamMembers = true;
        $userMock->expects(static::once())->method('canAddTeamMembers')->willReturn($canAddTeamMembers);
        $userMock->setAttribute('id', $owner->id);

        $this->app->instance('User', $userMock);

        $response = $this->json('POST', route('remove', ['id' => $teamMember->id]));

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonStructure(['code', 'message', 'data']);
        $response->assertJsonFragment(['code' => Response::HTTP_OK]);
        $response->assertJsonFragment(['message' => 'Team Member Deleted Successfully.']);
        $response->assertJsonFragment(['data' => ['hide_create_button' => !$canAddTeamMembers]]);
        $this->assertDatabaseMissing('owner_to_team_members', ['id' => $ownerToTeamMember->id]);
        $this->assertDatabaseMissing('team_member_group_access', ['owner_to_team_member_id' => $ownerToTeamMember->id]);
    }

    /**
     * @test
     * that checkTeamMembersEmail returns count value according to the team member exists in owner team
     *
     * @covers ::checkTeamMembersEmail
     *
     * @dataProvider checkTeamMembersEmail_withVariousExistStateProvider
     *
     * @param bool $exist in the owner's team, true if exist, otherwise false
     */
    public function checkTeamMembersEmail_withVariousExistState_returnsSuccessResponse(bool $exist)
    {
        $owner = $this->actingAsUser();
        $teamMembers = $this->createTeamMembersSetUp($owner);
        $teamMember = User::with('teamMemberGroupAccess')->find($teamMembers[0]->id);

        $response = $this->json('POST', route('checkTeamMembersEmail', [
            'email' => $exist ? $teamMember->email : 'jonny.evans@gmail.com'
        ]));

        $this->assertAuthenticated();
        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonStructure(['count', 'data', 'message']);
        $response->assertJsonFragment(['count' => intval($exist)]);
        $response->assertJsonFragment(['message' => 'successfully']);
        if ($exist) {
            $response->assertJsonFragment(['name' => $teamMember->name]);
            $response->assertJsonFragment(['id' => $teamMember->id]);
        } else {
            $response->assertJsonFragment(['data' => null]);
        }
    }

    /**
     * Data provider for {@see checkTeamMembersEmail_withVariousExistState_returnsSuccessResponse}
     *
     * @return array including exist in owner team parameter
     */
    public function checkTeamMembersEmail_withVariousExistStateProvider()
    {
        return [
            ['exist' => true],
            ['exist' => false],
        ];
    }

    /**
     * @test
     * that getEmail returns all users that contain similar email as requested
     *
     * @covers ::getEmail
     */
    public function getEmail_whenThereIsMatchingEmails_returnsFormattedEmails()
    {
        $owner = $this->actingAsUser();
        $this->createTeamMembersSetUp($owner);
        $jonnyDoe = User::factory(['email' => 'jonny.doe@gmail.com'])->create();
        $janeDoe = User::factory(['email' => 'jane.doe@gmail.com'])->create();

        $response = $this->json('POST', route('getEmail', ['search' => 'doe@gmail.com']));

        $this->assertAuthenticated();
        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonFragment(['value' => $jonnyDoe->id, 'label' => $jonnyDoe->email]);
        $response->assertJsonFragment(['value' => $janeDoe->id, 'label' => $janeDoe->email]);
    }

    /**
     * @test
     * that getEmail returns empty response if there is no email similar to the requested email
     *
     * @covers ::getEmail
     */
    public function getEmail_returnsEmptyResponse()
    {
        $owner = $this->actingAsUser();
        $this->createTeamMembersSetUp($owner);
        User::factory(['email' => 'john.johnson@gmail.com'])->create();
        User::factory(['email' => 'john.doe@outlook.com'])->create();

        $response = $this->json('POST', route('getEmail', ['search' => 'samuel@gmail.com']));

        $this->assertAuthenticated();
        $response->assertStatus(Response::HTTP_OK);
        $response->assertJson([]);
    }

    /**
     * Creates team members for the provided owner {@see User}
     *
     * @param User $owner
     * @return Collection of the team members
     */
    private function createTeamMembersSetUp(User $owner): Collection
    {
        $teamMembers = User::factory(5)->create();
        $ownerToTeamMembersData = $teamMembers->map(function ($teamMember) use ($owner) {
            return [
                'team_member_id' => $teamMember->id,
                'owner_id'       => $owner->id,
            ];
        })->toArray();

        OwnerToTeamMember::insert($ownerToTeamMembersData);
        $this->actingAs($owner);

        return $teamMembers;
    }
}
