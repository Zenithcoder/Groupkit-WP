<?php

namespace Tests\Unit\app\Http\Controllers\API;

use App\AutoResponder;
use App\FacebookGroups;
use App\GroupMembers;
use App\Http\Controllers\API\GroupController;
use App\OwnerToTeamMember;
use App\Services\TagService;
use App\Tag;
use App\TeamMemberGroupAccess;
use App\Traits\GroupTrait;
use App\User;
use Carbon\Carbon;
use Exception;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Passport\Passport;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;
use Tests\TestHelper;

/**
 * Class GroupControllerTest adds test coverage for {@see GroupController}
 *
 * @package Tests\Unit\app\Http\Controllers\API
 * @coversDefaultClass \App\Http\Controllers\Api\GroupController
 */
class GroupControllerTest extends TestCase
{
    use DatabaseTransactions;
    use DatabaseMigrations;
    use GroupTrait;

    /**
     * @var int that will be provided to the set_time_limit function
     */
    public const TIME_LIMIT_SECONDS = 360;

    /**
     * The Facebook Group id for creating test factory for the group
     *
     * @var int
     */
    public const FACEBOOK_GROUP_ID = 1;

    /**
     * Includes columns visibility properties for testing endpoints/methods
     *
     * @var array
     */
    public const COLUMNS_VISIBILITY = [
        'id' => false,
        'name' => true,
        'date_added' => true,
        'email' => true,
        'profile_id' => false,
        'respond_status' => true,
        'Q1_answer' => true,
        'Q2_answer' => true,
        'Q3_answer' => true,
        'phone_number' => true,
        'notes' => true,
        'tags' => true,
        'approved_by' => false,
        'invited_by' => false,
        'lives_in' => false,
        'agreed_group_rules' => false,
    ];

    /**
     * Includes columns visibility properties for testing endpoints/methods
     *
     * @var array
     */
    public const COLUMNS_WIDTH = [
        'id' => '1.3',
        'name' => '3.2',
        'date_added' => '5.1',
        'email' => '3.2',
        'profile_id' => '5.2',
        'respond_status' => '3.2',
        'Q1_answer' => '3.2',
        'Q2_answer' => '5.1',
        'Q3_answer' => '3.2',
        'phone_number' => '2.7',
        'notes' => '9.8',
        'tags' => '7.22',
        'approved_by' => '3.2',
        'invited_by' => '2.5',
        'lives_in' => '3.2',
        'agreed_group_rules' => '2.4',
    ];

    /**
     * @var User logged in the session
     */
    private User $user;

    /**
     * Setup test dependencies
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->user = $this->actingAsApiUser();
    }

    /**
     * @test
     * that the original action will be prevented from executing if the request validation fails and a JSON response
     * containing the validation message will be returned
     *
     * @covers \App\Http\Controllers\Traits\GroupkitControllerBehavior::getAjaxValidatorRules
     * @covers \App\Http\Middleware\ValidateAjaxRequest::handle
     *
     * @dataProvider handle_withVariousRequestParamsProvider
     *
     * @param string $requestType of the tested route
     * @param string $uri of the tested route
     * @param array $requestData containing key value pair params
     * @param string $expectedMessage of the tested method call
     */
    public function handle_withVariousRequestParamsProvider_returnsValidationMessage(
        string $requestType,
        string $uri,
        array $requestData,
        string $expectedMessage
    ) {
        $response = $this->call($requestType, "/api/groups/{$uri}", $requestData);

        $response->assertStatus(Response::HTTP_BAD_REQUEST);
        $response->assertJsonStructure(['message', 'data']);
        $response->assertJsonFragment([
            'message' => $expectedMessage,
            'data'    => [],
        ]);
    }

    /**
     * Data provider for {@see handle_withVariousRequestParamsProvider_returnsValidationMessage}
     *
     * @return array[] containing route request type, uri of the route, request data
     * and expected message of the tested method call
     */
    public function handle_withVariousRequestParamsProvider(): array
    {
        return [
            # Validation test cases for addMembers method
            'Group is required field for addMembers method' => [
                'requestType'     => 'POST',
                'uri'             => 'addMembers',
                'requestData'     => [
                    'members' => [
                        ['first'],
                        ['second'],
                    ],
                ],
                'expectedMessage' => 'The group field is required.',
            ],
            'Members is required field for addMembers method' => [
                'requestType'     => 'POST',
                'uri'             => 'addMembers',
                'requestData'     => [
                    'group' => [
                        ['first'],
                    ],
                ],
                'expectedMessage' => 'The members field is required.',
            ],
            'Group need to be array field for addMembers method' => [
                'requestType'     => 'POST',
                'uri'             => 'addMembers',
                'requestData'     => [
                    'members' => [
                        ['first'],
                        ['second'],
                    ],
                    'group' => 332,
                ],
                'expectedMessage' => 'The group must be an array.',
            ],
            'Members need to be array field for addMembers method' => [
                'requestType'     => 'POST',
                'uri'             => 'addMembers',
                'requestData'     => [
                    'group' => [
                        ['first'],
                    ],
                    'members' => 32,
                ],
                'expectedMessage' => 'The members must be an array.',
            ],
        ];
    }

    /**
     * Setup method adds group members for the provided facebook group ids
     *
     * @param array $facebookGroupIds of the {@see FacebookGroups} that belongs to {@see User}
     * @param int $userId represents the id of the Facebook groups owner
     *
     * @return array with
     * group member added today
     * group member added this week
     * email added today
     * email added this week
     */
    private function groupMembersSetUp(array $facebookGroupIds, int $userId): array
    {
        $defaultTimezone = config('app.timezone');

        $randomToday = Carbon::now($this->user->timezone)
            ->startOfDay()
            ->addHours(rand(0, 23))
            ->setTimezone($defaultTimezone);

        /*
        * dayOfWeek is a variable that represents the day of the week,
        * it can have values from 0 to 6,
        * from 1 to 6 corresponds to the days from Monday to Saturday,
        * and 0 is Sunday
        */
        $todayDayOfTheWeek = Carbon::now($this->user->timezone)
            ->setTimezone($defaultTimezone)
            ->dayOfWeek;

        /*
        * This variable is needed to find a random day from Monday to today,
        * in case today's day Sunday needs to be marked with the number 7
        * to find a random day from Monday to Sunday
        */
        if ($todayDayOfTheWeek == 0) {
            $todayDayOfTheWeek = 7;
        }

        $randomDayOfWeek = Carbon::now($this->user->timezone)
            ->startOfWeek()
            ->addDays(rand(0, max($todayDayOfTheWeek - 2, 0)))
            ->addHours(rand(0, 23))
            ->setTimezone($defaultTimezone);

        # sets dynamically day of this week that is not today
        $groupMembersAddedTodayCount = 0;
        $emailsAddedTodayCount = 0;
        $groupMembersAddedThisWeekCount = 0;
        $emailsAddedThisWeekCount = 0;

        foreach ($facebookGroupIds as $facebookGroupId) {
            $groupMembersAddedToday = GroupMembers::factory(rand(0, 10))->create([
                'user_id'       => $userId,
                'group_id'      => $facebookGroupId,
                'is_approved'   => 1,
                'date_add_time' => $randomToday,
            ]);
            $emailsAddedToday = GroupMembers::factory(rand(0, 10))->create([
                'user_id'           => $userId,
                'group_id'          => $facebookGroupId,
                'respond_status'    => GroupMembers::RESPONSE_STATUSES['ADDED'],
                'respond_date_time' => $randomToday,
            ]);
            $groupMembersAddedThisWeek = GroupMembers::factory(rand(0, 10))->create([
                'user_id'       => $userId,
                'group_id'      => $facebookGroupId,
                'is_approved'   => 1,
                'date_add_time' => $randomDayOfWeek,
            ]);
            $emailsAddedThisWeek = GroupMembers::factory(rand(0, 10))->create([
                'user_id'           => $userId,
                'group_id'          => $facebookGroupId,
                'respond_status'    => GroupMembers::RESPONSE_STATUSES['ADDED'],
                'respond_date_time' => $randomDayOfWeek,
            ]);

            $groupMembersAddedTodayCount += $groupMembersAddedToday->count();
            $groupMembersAddedThisWeekCount += $groupMembersAddedThisWeek->count();
            $emailsAddedTodayCount += $emailsAddedToday->count();
            $emailsAddedThisWeekCount += $emailsAddedThisWeek->count();
        }

        return [
            $groupMembersAddedTodayCount + ($todayDayOfTheWeek == 1 ? $groupMembersAddedThisWeekCount : 0),
            $groupMembersAddedThisWeekCount + $groupMembersAddedTodayCount, #total members added this week
            $emailsAddedTodayCount + ($todayDayOfTheWeek == 1 ? $emailsAddedThisWeekCount : 0),
            $emailsAddedThisWeekCount + $emailsAddedTodayCount, #total emails added this week
        ];
    }

    /**
     * @test
     * that index returns:
     * 1. all groups that belongs to the user
     * 2. count of all week members
     * 3. count of all today members
     * 4. count of all emails added in this week
     * 5. count of all emails added today
     * when user is logged as group owner
     *
     * @covers ::index
     * @covers ::filterGroups
     */
    public function index_withGroupOwnerAccount_returnsGroupDetails()
    {
        $facebookGroups = FacebookGroups::factory(3)->create(['user_id' => $this->user->id]);
        [
            $todaysMembersCount,
            $weeksMembersCount,
            $todaysEmailsAddedCount,
            $weeksEmailsAddedCount,
        ] = $this->groupMembersSetUp($facebookGroups->pluck('id')->toArray(), $this->user->id);

        $response = $this->get(route('getGroups'));

        $response->assertOk();
        $response->assertJsonStructure(['code', 'data']);
        $response->assertJsonFragment([
            'code'                      => Response::HTTP_OK,
            'weeks_members_count'       => $weeksMembersCount,
            'todays_members_count'      => $todaysMembersCount,
            'weeks_emails_added_count'  => $weeksEmailsAddedCount,
            'todays_emails_added_count' => $todaysEmailsAddedCount,
        ]);
        foreach ($facebookGroups as $facebookGroup) {
            $response->assertJsonFragment([
                'fb_id'   => (string)$facebookGroup->fb_id,
                'fb_name' => $facebookGroup->fb_name,
                'user_id' => (string)$this->user->id,
            ]);
        }
    }

    /**
     * @test
     * that index returns:
     * 1. all groups that team member has access to
     * 2. count of all week members of the owner's groups
     * 3. count of all today members of the owner's groups
     * 4. count of all emails added in this week of the owner's groups
     * 5. count of all emails added today of the owner's groups
     * when user is logged in as a team member without owned groups
     *
     * @covers ::index
     * @covers ::filterGroups
     */
    public function index_withTeamMemberAccount_returnsGroupDetails()
    {
        $owner = User::factory()->create();
        $teamMember = $this->user;
        $ownerTeamMember = OwnerToTeamMember::factory()->create([
            'team_member_id' => $teamMember->id,
            'owner_id'       => $owner->id,
        ]);
        $facebookGroups = FacebookGroups::factory(3)->create(['user_id' => $owner->id]);

        foreach ($facebookGroups as $facebookGroup) {
            TeamMemberGroupAccess::factory()->create([
                'user_id'                 => $teamMember->id,
                'facebook_group_id'       => $facebookGroup->id,
                'owner_to_team_member_id' => $ownerTeamMember->id,
            ]);
        }

        [
            $todaysMembersCount,
            $weeksMembersCount,
            $todaysEmailsAddedCount,
            $weeksEmailsAddedCount,
        ] = $this->groupMembersSetUp($facebookGroups->pluck('id')->toArray(), $owner->id);

        $response = $this->get(route('getGroups'));

        $response->assertOk();
        $response->assertJsonStructure(['code', 'data']);
        $response->assertJsonFragment([
            'code'                      => Response::HTTP_OK,
            'weeks_members_count'       => $weeksMembersCount,
            'todays_members_count'      => $todaysMembersCount,
            'weeks_emails_added_count'  => $weeksEmailsAddedCount,
            'todays_emails_added_count' => $todaysEmailsAddedCount,
        ]);
        foreach ($facebookGroups as $facebookGroup) {
            $response->assertJsonFragment([
                'fb_id'   => (string)$facebookGroup->fb_id,
                'fb_name' => $facebookGroup->fb_name,
                'user_id' => (string)$owner->id,
            ]);
        }
    }

    /**
     * @test
     * that groupFilterByID returns:
     * 1. all groups details that belong to the user
     * 2. week member count of the requested group
     * 3. today member count of the requested group
     * 4. count of email added in this week of the requested group
     * 5. count of email added today of the requested group
     *
     * @covers ::groupFilterByID
     * @covers ::filterGroups
     */
    public function filterGroups_currentUserNotHavingOwnedGroup_getGroupsDetails()
    {
        $otherGroups = FacebookGroups::factory(5)->create(['user_id' => $this->user->id]);
        $this->groupMembersSetUp($otherGroups->pluck('id')->toArray(), $this->user->id);

        $facebookGroup = FacebookGroups::factory()->create(['user_id' => $this->user->id]);
        [
            $todaysMembersCount,
            $weeksMembersCount,
            $todaysEmailsAddedCount,
            $weeksEmailsAddedCount,
        ] = $this->groupMembersSetUp([$facebookGroup->id], $this->user->id);

        $response = $this->get(route('groupsByID', ['id' => $facebookGroup->id]));

        $response->assertOk();
        $response->assertJsonStructure(['code', 'data']);
        $response->assertSee('groups');
        $response->assertJsonFragment([
            'code'                      => Response::HTTP_OK,
            'weeks_members_count'       => $weeksMembersCount,
            'todays_members_count'      => $todaysMembersCount,
            'weeks_emails_added_count'  => $weeksEmailsAddedCount,
            'todays_emails_added_count' => $todaysEmailsAddedCount,
            'id'                        => $facebookGroup->id,
            'fb_id'                     => (string)$facebookGroup->fb_id,
            'fb_name'                   => $facebookGroup->fb_name,
        ]);

        foreach ($otherGroups as $group) {
            $response->assertJsonFragment([
                'id'      => $group->id,
                'fb_id'   => (string)$group->fb_id,
                'fb_name' => $group->fb_name,
            ]);
        }
    }

    /**
     * @test
     * that groupDetails returns
     * 1. group details
     * 2. members details
     * 3. responder details
     * for provided {@see FacebookGroups} id
     *
     * @covers ::groupDetails
     */
    public function groupDetails_always_returnsFacebookGroupWithMembersAndResponder()
    {
        $facebookGroup = FacebookGroups::factory()->create(['user_id' => $this->user->id]);
        $uniqueKeys = [
            'user_id'  => $this->user->id,
            'group_id' => $facebookGroup->id,
        ];
        $integration = AutoResponder::factory()->create($uniqueKeys);
        $members = GroupMembers::factory(20)->create($uniqueKeys);

        $response = $this->get(route('groups', ['id' => $facebookGroup->id]));

        $response->assertOk();
        $response->assertJsonStructure(['code', 'data']);
        $response->assertJsonFragment([
            'code'           => Response::HTTP_OK,
            'fb_id'          => (string)$facebookGroup->fb_id,
            'fb_name'        => $facebookGroup->fb_name,
            'responder_type' => $integration->responder_type,
            'members_count' => [
                'group_id' => (string)$facebookGroup->id,
                'members' => (string)$members->count(),
            ],
        ]);
        $response->assertSee('group');
        $response->assertSee('members');
        $response->assertSee('responder');
    }

    /**
     * @test
     * that destroy soft deletes
     * 1. {@see FacebookGroups} with provided id
     * 2. All group's members
     * 3. Group integration
     *
     * @covers ::destroy
     */
    public function destroy_happyPath_softDeletesGroup()
    {
        $facebookGroup = FacebookGroups::factory()->create(['user_id' => $this->user->id]);
        $uniqueKeys = [
            'user_id'  => $this->user->id,
            'group_id' => $facebookGroup->id,
        ];
        $integration = AutoResponder::factory()->create($uniqueKeys);
        $members = GroupMembers::factory(10)->create($uniqueKeys);

        $response = $this->get(route('groupsDelete', ['id' => $facebookGroup->id]));

        $response->assertOk();
        $response->assertJsonStructure(['code', 'message', 'data']);
        $response->assertJsonFragment([
            'code'    => Response::HTTP_OK,
            'message' => 'Your group has been removed.',
            'data'    => '',
        ]);
        $this->assertSoftDeleted('facebook_groups', [
            'id'      => $facebookGroup->id,
            'fb_name' => $facebookGroup->fb_name,
            'fb_id'   => $facebookGroup->fb_id,
        ]);
        $this->assertSoftDeleted('auto_responder', [
            'id'             => $integration->id,
            'responder_type' => $integration->responder_type,
        ]);

        foreach ($members as $member) {
            $this->assertSoftDeleted('group_members', [
                'id'     => $member->id,
                'f_name' => $member->f_name,
                'l_name' => $member->l_name,
                'email'  => $member->email,
            ]);
        }
    }

    /**
     * @test
     * that destroy returns {@see Response::HTTP_UNAUTHORIZED} response code
     * if the user not own {@see FacebookGroups} with provided id
     *
     * @covers ::destroy
     */
    public function destroy_ifUserDoesntOwnGroup_returnsHTTPUnauthorizedCode()
    {
        $facebookGroup = FacebookGroups::factory()->create();

        $response = $this->get(route('groupsDelete', ['id' => $facebookGroup->id]));

        $response->assertOk();
        $response->assertJsonStructure(['code', 'message', 'data']);
        $response->assertJsonFragment([
            'code'    => Response::HTTP_UNAUTHORIZED,
            'message' => 'You do not have access to delete this group.',
            'data'    => '',
        ]);
        $this->assertDatabaseHas('facebook_groups', [
            'id'      => $facebookGroup->id,
            'fb_id'   => $facebookGroup->fb_id,
            'fb_name' => $facebookGroup->fb_name,
        ]);
    }

    /**
     * @test
     * that destroy returns exception message and doesn't deletes:
     * 1. {@see FacebookGroups} with provided id
     * 2. All group's members
     * 3. Group integration
     * if exception is thrown.
     *
     * @covers ::destroy
     */
    public function destroy_whenExceptionIsThrown_returnsExceptionMessage()
    {
        $facebookGroup = FacebookGroups::factory()->create(['user_id' => $this->user->id]);
        $uniqueKeys = [
            'user_id'  => $this->user->id,
            'group_id' => $facebookGroup->id,
        ];
        $integration = AutoResponder::factory()->create($uniqueKeys);
        $members = GroupMembers::factory(10)->create($uniqueKeys);
        $exceptionMessage = 'Something went wrong';
        $facebookGroupsMock = $this->getMockBuilder(FacebookGroups::class)
            ->onlyMethods(['delete'])
            ->addMethods(['find'])
            ->getMock();
        $facebookGroupsMock->expects(static::once())->method('find')->with($facebookGroup->id)
            ->willReturnSelf();
        $facebookGroupsMock->expects(static::once())->method('delete')
            ->willThrowException(new Exception($exceptionMessage));
        $this->mock(FacebookGroups::class)
            ->shouldReceive('where')->with('user_id', $this->user->id)
            ->andReturn($facebookGroupsMock);

        $response = $this->get(route('groupsDelete', ['id' => $facebookGroup->id]));

        $response->assertOk();
        $response->assertJsonStructure(['code', 'message', 'data']);
        $response->assertJsonFragment([
            'code'    => Response::HTTP_INTERNAL_SERVER_ERROR,
            'message' => $exceptionMessage,
            'data'    => '',
        ]);
        $this->assertDatabaseHas('facebook_groups', [
            'id'      => $facebookGroup->id,
            'fb_name' => $facebookGroup->fb_name,
            'fb_id'   => $facebookGroup->fb_id,
        ]);
        $this->assertDatabaseHas('auto_responder', [
            'id'             => $integration->id,
            'responder_type' => $integration->responder_type,
        ]);

        foreach ($members as $member) {
            $this->assertDatabaseHas('group_members', [
                'id'     => $member->id,
                'f_name' => $member->f_name,
                'l_name' => $member->l_name,
                'email'  => $member->email,
            ]);
        }
    }

    /**
     * @test
     * that Autoresponder stores new autoresponder when there is no same autoresponder in the database
     *
     * @covers ::Autoresponder
     */
    public function Autoresponder_whenAutorespondIsNotInTheDatabase_storesAutoresponder()
    {
        $facebookGroup = FacebookGroups::factory()->create(['user_id' => $this->user->id]);

        $requestMock = $this->partialMock(GroupController::class);

        $responderData = [
            'activeList' => ['label' => '', 'value' => 1],
            'sheetURL'   => 'https://docs.google.com/spreadsheets/d/10JTYyl5Sg7QAZ5uxc4YDbI8GnqF_u3WN0d1jl7UWn1M',
        ];

        $autoresponder = [
            'responder_type' => 'GoogleSheet',
            'group_id'       => (string)$facebookGroup->id,
            'user_id'        => (string)$this->user->id,
            'responder_json' => json_encode($responderData),
            'is_check'       => 1,
        ];

        $this->assertDatabaseMissing('auto_responder', $autoresponder);

        $requestMock->Autoresponder($responderData, 'GoogleSheet', $facebookGroup->id, $this->user->id);

        $this->assertDatabaseHas('auto_responder', $autoresponder);
    }

    /**
     * @test
     * that Autoresponder updates autoresponder with responder data and responder type
     * when there is autoresponder for the user and the requested group in the database
     *
     * @covers ::Autoresponder
     */
    public function Autoresponder_whenAutorespondIsInTheDatabase_updatesAutoresponder()
    {
        $facebookGroup = FacebookGroups::factory()->create(['user_id' => $this->user->id]);
        $autoresponder = [
            'responder_type' => 'GoogleSheet',
            'group_id'       => (string)$facebookGroup->id,
            'user_id'        => (string)$this->user->id,
            'responder_json' => json_encode(['activeList' => ['label' => '', 'value' => 1]]),
            'is_check'       => 1,
        ];
        AutoResponder::factory()->create($autoresponder);

        $requestMock = $this->partialMock(GroupController::class);

        $responderData = [
            'activeList' => ['label' => 'FB Group', 'value' => 1423423],
            'api_key'    => '0C32aXkrGsIA_Q4B4eA',
        ];

        $this->assertDatabaseHas('auto_responder', $autoresponder);

        $requestMock->Autoresponder($responderData, 'Aweber', $facebookGroup->id, $this->user->id);

        $this->assertDatabaseMissing('auto_responder', $autoresponder);
        $this->assertDatabaseHas('auto_responder', [
            'responder_type' => 'Aweber',
            'group_id'       => (string)$facebookGroup->id,
            'user_id'        => (string)$this->user->id,
            'responder_json' => json_encode($responderData),
            'is_check'       => 1,
        ]);
    }

    /**
     * @test
     * that importTextFile returns error message with {@see Response::HTTP_BAD_REQUEST} code
     * when user try to import invalid import file
     *
     * @covers ::importTextFile
     */
    public function importTextFile_whenUserImportIncompleteFile_returnsHTTPBadRequestCode()
    {
        $facebookGroup = FacebookGroups::factory()->create(['user_id' => $this->user->id]);
        $requestData = [
            'fbids'   => [$facebookGroup->fb_id],
            'fbnames' => [],
        ];

        static::$functions->shouldReceive('set_time_limit')->with(static::TIME_LIMIT_SECONDS)->once();

        $response = $this->json('POST', route('groups.import', $requestData));

        $response->assertOk();
        $response->assertJsonStructure(['code', 'message', 'data']);
        $response->assertJsonFragment([
            'code'    => Response::HTTP_BAD_REQUEST,
            'message' => 'The imported file seems to be incomplete. Please try again by uploading a new file.',
            'data'    => '',
        ]);
    }

    /**
     * Setup method for mocking user
     *
     * @return FacebookGroups created in the database for testing methods
     */
    private function userCantAddMoreGroupsSetUp()
    {
        $userMock = $this->getMockBuilder(User::class)
            ->onlyMethods(['canAddAnother', 'getOwnedGroupByFacebookId'])
            ->setProxyTarget($this->user)
            ->disableOriginalConstructor()
            ->getMock();
        $this->actingAs($userMock);
        $facebookGroup = FacebookGroups::factory()->make();

        $userMock->expects(static::once())->method('canAddAnother')->with('group')->willReturn(false);
        $userMock->expects(static::once())
            ->method('getOwnedGroupByFacebookId')
            ->with($facebookGroup->fb_id)
            ->willReturn(false);

        $this->app->instance('User', $userMock);

        return $facebookGroup;
    }

    /**
     * @test
     * that importTextFile prevents the user to add group over the plan limit
     *
     * @covers ::importTextFile
     */
    public function importTextFile_whenUserTriesToAddGroupOverTheLimit_returnsUpgradeMessage()
    {
        $facebookGroup = $this->userCantAddMoreGroupsSetUp();
        $requestData = [
            'fbids'   => [$facebookGroup->fb_id],
            'fbnames' => [$facebookGroup->fb_name],
        ];

        static::$functions->shouldReceive('set_time_limit')->with(static::TIME_LIMIT_SECONDS)->once();

        $response = $this->json('POST', route('groups.import', $requestData));

        $response->assertOk();
        $response->assertJsonStructure(['code', 'message']);
        $response->assertJsonFragment([
            'code'    => Response::HTTP_FORBIDDEN,
            'message' => "Your plan's group limit has been reached. " . $this->upgradePlanLink(),
        ]);
    }

    /**
     * Setup for {@see importTextFile_whenExceptionIsThrown_returnsHTTPBadRequestCode}
     *
     * @return array containing request data
     */
    private function importTextFileSetUp()
    {
        $facebookGroup = FacebookGroups::factory()->create(['user_id' => $this->user->id]);
        $dataArray = [
            'notes'          => '',
            'rowid'          => '1',
            'f_name'         => 'Makk',
            'l_name'         => 'Adesra',
            'email'          => '-',
            'respond_status' => 'No Email',
            'user_id'        => '1',
            'date_add_time'  => '1599637080',
            'a1'             => '',
            'a2'             => '',
            'a3'             => '',
            'fb_id'          => $facebookGroup->fb_id,
            'id'             => '1',
        ];

        return [
            'fbids'      => [$facebookGroup->fb_id],
            'fbnames'    => [$facebookGroup->fb_name],
            'data'       => [$dataArray],
            #aweber request data is invalid
            'aweber'     => [
                'fb_id'         => $facebookGroup->fb_id,
                'list_name'     => '',
                'list_id'       => '',
                'access_token'  => '',
                'refresh_token' => '',
                'acc_id'        => '',
            ],
        ];
    }

    /**
     * @test
     * that importTextFile stops the import and returns {@see Response::HTTP_BAD_REQUEST} code
     * if request data for aweber service is invalid
     *
     * @covers ::importTextFile
     */
    public function importTextFile_whenExceptionIsThrown_returnsHTTPBadRequestCode()
    {
        $requestData = $this->importTextFileSetUp();

        static::$functions->shouldReceive('set_time_limit')->with(static::TIME_LIMIT_SECONDS)->once();

        $response = $this->json('POST', route('groups.import', $requestData));

        $response->assertOk();
        $response->assertJsonStructure(['code', 'message', 'data']);
        $response->assertJsonFragment([
            'code'    => Response::HTTP_BAD_REQUEST,
            'message' => 'Invalid Request',
            'data'    => '',
        ]);
    }

    /**
     * SetUp for {@see importTextFile_happyPath_returnsSuccessMessage}
     *
     * @return array containing request data with all integrations
     */
    private function importTextFile_happyPathSetUp()
    {
        return [
            'fbids' => [
                7831485,
                33088812,
                12089657,
                17807209,
                25378180,
                42390409,
                8011627,
                16232564,
                14260916,
            ],
            'fbnames' => [
                'The Network Marketing Way, for Rookies',
                'Diet vs Disease Community (Members Only)',
                '5-Day Weight Loss & IBS Challenge Support Group',
                'Diet vs Disease - 7 Day Weight Loss Challenge',
                'Codependency, Healing and Creating Healthy Relationships',
                '😎 #SocialCoachPRO 🚀',
                '#Replay ⏪',
                '🤓 #TalkNerdyToMe 🚀',
                '☀️ #aGoodYou 🚀',
            ],
            'fbbool' => [true,true,true,true,true,true,true,true,true],
            'data' => [
                [
                    'fb_id'          => '7831485',
                    'rowid'          => 1,
                    'f_name'         => 'Paul',
                    'l_name'         => 'Johnson',
                    'email'          => '-',
                    'user_id'        => '1493889115',
                    'a1'             => '',
                    'a2'             => '',
                    'a3'             => '',
                    'respond_status' => 'No Email',
                    'date_add_time'  => 1603891184,
                    'id'             => 1,
                ],
                [
                    'fb_id'          => '33088812',
                    'rowid'          => 2,
                    'f_name'         => 'Marilu',
                    'l_name'         => 'Maldonado',
                    'email'          => '-',
                    'user_id'        => '1317480860',
                    'a1'             => '',
                    'a2'             => '',
                    'a3'             => '',
                    'respond_status' => 'Added',
                    'date_add_time'  => 1603906825,
                    'id'             => 2,
                ],
                [
                    'fb_id'          => '12089657',
                    'rowid'          => 3,
                    'f_name'         => 'Julia',
                    'l_name'         => 'Wadrop',
                    'email'          => 'Infobuniquebeauty@gmail.com',
                    'user_id'        => '1078571401',
                    'a1'             => 'BUSINESS ',
                    'a2'             => 'ENGAGEMENT WITH NEW PPL HOW TO PROMOTE MY to new ppl',
                    'a3'             => 'Infobuniquebeauty@gmail.com ',
                    'respond_status' => 'Added',
                    'date_add_time'  => 1603981691,
                    'id'             => 3,
                ],
                [
                    'fb_id'          => '17807209',
                    'rowid'          => 4,
                    'f_name'         => 'Joey-Lori',
                    'l_name'         => 'Waldman',
                    'email'          => '-',
                    'user_id'        => '502975226',
                    'a1'             => '',
                    'a2'             => '',
                    'a3'             => '',
                    'respond_status' => 'Added',
                    'date_add_time'  => 1603981844,
                    'id'             => 4,
                ],
                [
                    'fb_id'          => '25378180',
                    'rowid'          => 5,
                    'f_name'         => 'Stefanie',
                    'l_name'         => 'Kenoyer DelGreco',
                    'email'          => '-',
                    'user_id'        => '654506703',
                    'a1'             => '',
                    'a2'             => '',
                    'a3'             => '',
                    'respond_status' => 'Added',
                    'date_add_time'  => 1603981865,
                    'id'             => 5,
                ],
                [
                    'fb_id'          => '42390409',
                    'rowid'          => 6,
                    'f_name'         => 'Debbie',
                    'l_name'         => 'Weiner Charpiat',
                    'email'          => 'ally44@hotmail.com',
                    'user_id'        => '687298788',
                    'a1'             => 'time and finding the right people ',
                    'a2'             => 'ally44@hotmail.com',
                    'a3'             => 'BUSINESS',
                    'respond_status' => 'Added',
                    'date_add_time'  => 1603981867,
                    'id'             => 6,
                ],
                [
                    'fb_id'          => '8011627',
                    'rowid'          => 7,
                    'f_name'         => 'Nicole',
                    'l_name'         => 'Brantley Laidlaw',
                    'email'          => '-',
                    'user_id'        => '1017858791',
                    'a1'             => '',
                    'a2'             => '',
                    'a3'             => '',
                    'respond_status' => 'Added',
                    'date_add_time'  => 1603981901,
                    'id'             => 7,
                ],
                [
                    'fb_id'          => '16232564',
                    'rowid'          => 8,
                    'f_name'         => 'Jane',
                    'l_name'         => 'Newberry',
                    'email'          => '-',
                    'user_id'        => '685123592',
                    'a1'             => '',
                    'a2'             => '',
                    'a3'             => '',
                    'respond_status' => 'Added',
                    'date_add_time'  => 1603981957,
                    'id'             => 8,
                ],
                [
                    'fb_id'          => '14260916',
                    'rowid'          => 9,
                    'f_name'         => 'Kelli',
                    'l_name'         => 'Morse',
                    'email'          => '-',
                    'user_id'        => '100005611896126',
                    'a1'             => '',
                    'a2'             => '',
                    'a3'             => '',
                    'respond_status' => 'Added',
                    'date_add_time'  => 1603982038,
                    'id'             => 9,
                ],
            ],
            'aweber'     => [
                [
                    'fb_id'         => 7831485,
                    'list_name'     => 'ERE',
                    'list_id'       => 5525025,
                    'access_token'  => 'sI314Lkymq7uwqUa5345jknfs',
                    'refresh_token' => '4542bkn34kfnBPRw1m1JIMRjXUlT',
                    'acc_id'        => '95245345',
                    'client_id'     => 'uN922fsr25435kjnndad34ndasd',
                    'expires_in'    => 1605545862,
                ],
            ],
            'getr'       => [
                [
                    'fb_id'     => 33088812,
                    'list_name' => 'barpri_optin',
                    'list_id'   => 'Woieas',
                    'apikey'    => 'f1f069b1f53e7352b728b1432fds',
                ],
            ],
            'conv'       => [
                [
                    'fb_id'     => 12089657,
                    'list_id'   => '1698395',
                    'apikey'    => '0PWwDyaP4434cHCcrg',
                    'list_name' => '#SocialCoachPRO',
                    'check'     => 'true',
                    'id'        => 1,
                ],
            ],
            'active'     => [
                [
                    'fb_id'    => 17807209,
                    'listid'   => '3',
                    'tagid'    => null,
                    'username' => 'sacredwar3dseting',
                    'apik'     => '1612776e4978ae4234fdssad0085684a897c5bfefe',
                ],
            ],
            'mailerlite' => [
                [
                    'fb_id'    => 25378180,
                    'listid'   => 312321,
                    'apikey'   => 'cdfs213',
                    'password' => 'QbZvF312dast',
                    'app_id'   => 'YpE1dsa3RItQUab',
                ],
            ],
            'sheets'     => [
                [
                    'fb_id' => 42390409,
                    'sheet' => '1R6j8IayyhAAbWUFfXYi32dm32-4TGH423234-429c0LLpdw',
                    'id'    => 4,
                ],
            ],
            'mailchimp'  => [
                [
                    'fb_id'   => 8011627,
                    'list_id' => 'YpEJO3das21tQUab',
                    'apik'    => 'x3das21CJAoR',
                ],
            ],
            'kantra'     => [
                [
                    'fb_id'    => 16232564,
                    'username' => 'xqyC3ss2AoR',
                    'apik'     => 'QbZvFyd2ss3Tcpt',
                    'listid'   => 'YpE4s2RItQUab',
                ],
            ],
            'gohigh'     => [
                [
                    'fb_id'   => 14260916,
                    'apik'    => 'xqyCJ23d2',
                    'list_id' => 'YpEJ332dsdaQUab',
                ],
            ],
            'keywords'   => [
                ['keyword' => 'MESSAGED', 'user_id' => 8, 'fb_id' => 16232564],
                ['keyword' => 'ANSWERED', 'user_id' => 8, 'fb_id' => 14260916],
            ],
        ];
    }

    /**
     * @test
     * that importTextFile returns success message with {@see Response::HTTP_OK} if everything run successfully
     *
     * @covers ::importTextFile
     * @covers ::importGroupMembersTags
     */
    public function importTextFile_happyPath_returnsSuccessMessage()
    {
        $requestData = $this->importTextFile_happyPathSetUp();

        $userMock = $this->getMockBuilder(User::class)
            ->onlyMethods(['getOwnedGroupByFacebookId'])
            ->setProxyTarget($this->user)
            ->disableOriginalConstructor()
            ->getMock();
        $userMock->setAttribute('id', $this->user->id);
        $userMock->expects(static::exactly(9))
            ->method('getOwnedGroupByFacebookId')
            ->withConsecutive(
                [$requestData['fbids'][0]],
                [$requestData['fbids'][1]],
                [$requestData['fbids'][2]],
                [$requestData['fbids'][3]],
                [$requestData['fbids'][4]],
                [$requestData['fbids'][5]],
                [$requestData['fbids'][6]],
                [$requestData['fbids'][7]],
                [$requestData['fbids'][8]]
            )
            ->willReturn(true);

        Passport::actingAs($userMock);

        $this->mock(GroupMembers::class)
            ->shouldReceive('upsert')
            ->withSomeOfArgs(['user_id', 'fb_id', 'group_id']);

        static::$functions->shouldReceive('set_time_limit')->with(static::TIME_LIMIT_SECONDS)->once();

        $response = $this->postJson('/api/groups/import', $requestData);

        $response->assertOk();
        $response->assertJsonStructure(['code', 'message', 'data']);
        $response->assertJsonFragment([
            'code'    => Response::HTTP_OK,
            'message' => 'Your group members have been imported.',
            'data'    => '',
        ]);

        $autoresponders = [
            'Aweber',
            'Getresponse',
            'ConvertKit',
            'ActiveCampaign',
            'Mailerlite',
            'GoogleSheet',
            'MailChimp',
            'Kartra',
            'GoHighLevel',
        ];

        for ($i = 0; $i < count($requestData['fbids']); $i++) {
            $this->assertDatabaseHas('facebook_groups', [
                'fb_id'   => $requestData['fbids'][$i],
                'fb_name' => $requestData['fbnames'][$i],
                'user_id' => $this->user->id,
            ]);

            $facebookGroup = FacebookGroups::where('fb_id', $requestData['fbids'][$i])
                ->where('fb_name', $requestData['fbnames'][$i])
                ->first();

            $this->assertDatabaseHas(
                'auto_responder',
                [
                    'responder_type' => $autoresponders[$i],
                    'user_id'        => $this->user->id,
                    'is_check'       => 1,
                    'group_id'       => $facebookGroup->id,
                ]
            );
        }
    }

    /**
     * @test
     * that importTextFile:
     * 1. resurrects group when requested group is soft deleted
     * 2. provides requested tags to the {@see \App\Services\TagService::bulkStoreOrUpdate} method
     *
     * @covers ::importTextFile
     * @covers ::importGroupMembersTags
     */
    public function importTextFile_withSoftDeletedGroup_resurrectGroup()
    {
        $facebookGroup = FacebookGroups::factory([
            'user_id'    => $this->user->id,
            'deleted_at' => now()->subDay(),
        ])->create();

        $dataArray = [
            'notes'          => '',
            'rowid'          => '1',
            'f_name'         => 'Makk',
            'l_name'         => 'Adesra',
            'email'          => 'test@gmail.com',
            'respond_status' => 'N/A',
            'user_id'        => '1',
            'date_add_time'  => '',
            'a1'             => '',
            'a2'             => '',
            'a3'             => '',
            'fb_id'          => $facebookGroup->fb_id,
            'id'             => '1',
        ];
        $requestData = [
            'fbids'    => [$facebookGroup->fb_id],
            'fbnames'  => [$facebookGroup->fb_name],
            'data'     => [$dataArray],
            'keywords' => [
                ['keyword' => 'MESSAGED', 'user_id' => 1, 'fb_id' => $facebookGroup->fb_id],
                ['keyword' => 'ANSWERED', 'user_id' => 1, 'fb_id' => $facebookGroup->fb_id],
            ],
        ];

        $this->mock(GroupMembers::class)
            ->shouldReceive('upsert')
            ->withSomeOfArgs(['user_id', 'fb_id', 'group_id']);

        $this->assertSoftDeleted('facebook_groups', [
            'id'      => $facebookGroup->id,
            'fb_id'   => $facebookGroup->fb_id,
            'fb_name' => $facebookGroup->fb_name,
        ]);

        $expectedTags = [array_column($requestData['keywords'], 'keyword')];

        $tagServiceMock = $this->mock(TagService::class);

        $tagServiceMock->shouldReceive('bulkStoreOrUpdate')
            ->withSomeOfArgs(
                $expectedTags,
                $facebookGroup->id,
                $this->user->id,
            );

        static::$functions->shouldReceive('set_time_limit')->with(static::TIME_LIMIT_SECONDS)->once();

        $response = $this->postJson('/api/groups/import', $requestData);

        $response->assertOk();
        $response->assertJsonStructure(['code', 'message', 'data']);
        $response->assertJsonFragment([
            'code'    => Response::HTTP_OK,
            'message' => 'Your group members have been imported.',
            'data'    => '',
        ]);
        $this->assertDatabaseHas('facebook_groups', [
            'id'         => $facebookGroup->id,
            'fb_id'      => $facebookGroup->fb_id,
            'fb_name'    => $facebookGroup->fb_name,
            'deleted_at' => null,
        ]);
    }

    /**
     * @test
     * that importCsv prevents the user to add group over the plan limit
     *
     * @covers ::importCsv
     */
    public function importCsv_whenUserTriesToAddGroupOverTheLimit_returnsUpgradeMessage()
    {
        $facebookGroup = $this->userCantAddMoreGroupsSetUp();

        $requestData = [
            'facebook_groups' => [
                'fb_id'   => $facebookGroup->fb_id,
                'fb_name' => $facebookGroup->fb_name,
            ],
        ];

        $response = $this->json('POST', route('groups.importCsv', $requestData));

        $this->assertAuthenticated();
        $response->assertOk();
        $response->assertJsonStructure(['code', 'message']);
        $response->assertJsonFragment([
            'code'    => Response::HTTP_FORBIDDEN,
            'message' => "Your plan's group limit has been reached. " . $this->upgradePlanLink(),
        ]);
    }

    /**
     * @test
     * that importCsv returns exception message while uploading invalid csv (group members is not an array)
     *
     * @covers ::importCsv
     */
    public function importCsv_uploadingInvalidCsv_returnsExceptionMessage()
    {
        $facebookGroup = FacebookGroups::factory(['user_id' => $this->user->id])->create();
        $requestData = [
            'facebook_groups' => [
                'fb_id' => $facebookGroup->fb_id,
                'fb_name' => $facebookGroup->fb_name,
            ],
            'file_name' => '547486429302265_GROUPKIT__Asia_Kolkata__1612966931.csv',
        ];

        $response = $this->json('POST', route('groups.importCsv', $requestData));

        $response->assertOk();
        $response->assertJsonStructure(['code', 'message', 'data']);
        $response->assertJsonFragment([
            'code' => Response::HTTP_INTERNAL_SERVER_ERROR,
            'message' => 'Oops!  There was a problem processing your import.  Please try again later.',
            'data' => ['error_code' => 0],
        ]);
    }

    /**
     * @test
     * that importCsv
     * 1. creates facebook group with requested facebook group data
     * 2. inserts facebook group members
     * 3. provides group member with tags to the
     * {@see \App\Http\Controllers\API\GroupController::importGroupMembersTags} method
     * 4. returns response with {@see Response::HTTP_OK} code
     * when request contain facebook group that is not in the database
     *
     * @covers ::importCsv
     * @covers ::importGroupMembersTags
     */
    public function importCsv_withNewGroup_storesFacebookGroup()
    {
        $groupMembers = [
            'tags'           => 'Messaged,Answered',
            'notes'          => '',
            'rowid'          => '1',
            'f_name'         => 'Rachel',
            'l_name'         => 'Schuyler',
            'email'          => 'hobos.with.god@gmail.com',
            'respond_status' => 'Not Added',
            'user_id'        => '100030422691065',
            'date_add_time'  => '01-13-2021 15:44',
            'a1'             => 'starting up',
            'a2'             => 'hobos.with.god@gmail.com',
            'a3'             => 'YES',
            'fb_id'          => '3102027389891885',
        ];

        $requestData = [
            'facebook_groups' => [
                'fb_id'   => 32143242321,
                'fb_name' => 'GroupKit: Technology To Increase Your Group Revenue',
            ],
            'group_members'   => [$groupMembers],
            'file_name'       => '32143242321_GROUPKIT_3312.csv',
        ];

        $this->assertDatabaseMissing('facebook_groups', [
            'user_id' => $this->user->id,
            'fb_id'   => $requestData['facebook_groups']['fb_id'],
            'fb_name' => $requestData['facebook_groups']['fb_name'],
        ]);

        $userMock = $this->getMockBuilder(User::class)
            ->onlyMethods(['canAddAnother', 'getOwnedGroupByFacebookId'])
            ->setProxyTarget($this->user)
            ->disableOriginalConstructor()
            ->getMock();
        $userMock->setAttribute('id', ['dsadas']);

        $userMock->expects(static::once())
            ->method('getOwnedGroupByFacebookId')
            ->with($requestData['facebook_groups']['fb_id'])
            ->willReturn(true);

        Passport::actingAs($userMock);

        $this->mock(GroupMembers::class)
            ->shouldReceive('upsert')
            ->withSomeOfArgs(['user_id', 'fb_id', 'group_id']);

        $response = $this->json('POST', route('groups.importCsv', $requestData));

        $response->assertOk();
        $response->assertJsonStructure(['code', 'message', 'data']);
        $response->assertJsonFragment([
            'code'    => Response::HTTP_OK,
            'message' => "Successfully imported data!\nUpdates may take a moment to be reflected in your dashboard.",
            'data'    => [],
        ]);

        $this->assertDatabaseHas('facebook_groups', [
            'user_id' => $this->user->id,
            'fb_id'   => $requestData['facebook_groups']['fb_id'],
            'fb_name' => $requestData['facebook_groups']['fb_name'],
        ]);
    }

    /**
     * @test
     * that importCsv
     * 1. resurrect soft deleted Facebook Group
     * 2. provides group member with tags to the
     * {@see \App\Http\Controllers\API\GroupController::importGroupMembersTags} method
     * 3. returns response with {@see Response::HTTP_OK} code
     * when request contain facebook group that is soft deleted
     *
     * @covers ::importCsv
     * @covers ::importGroupMembersTags
     */
    public function importCsv_withDeletedFacebookGroup_returnsHTTPOKCode()
    {
        $userMock = $this->getMockBuilder(User::class)
            ->onlyMethods(['canAddAnother', 'getOwnedGroupByFacebookId'])
            ->setProxyTarget($this->user)
            ->disableOriginalConstructor()
            ->getMock();
        $userMock->setAttribute('id', $this->user->id);
        $facebookGroup = FacebookGroups::factory([
            'user_id'    => $this->user->id,
            'deleted_at' => now()->subDay(),
        ])->create();

        $this->assertSoftDeleted('facebook_groups', [
            'id'      => $facebookGroup->id,
            'fb_id'   => $facebookGroup->fb_id,
            'fb_name' => $facebookGroup->fb_name,
        ]);

        $userMock->expects(static::once())
            ->method('getOwnedGroupByFacebookId')
            ->with($facebookGroup->fb_id)
            ->willReturn(true);

        Passport::actingAs($userMock);

        $groupMembers = [
            'tags'           => 'Messaged,Answered',
            'notes'          => '',
            'rowid'          => '1',
            'f_name'         => 'Rachel',
            'l_name'         => 'Schuyler',
            'email'          => 'hobos.with.god@gmail.com',
            'respond_status' => 'Not Added',
            'user_id'        => '100030422691065',
            'date_add_time'  => '01-13-2021 15:44',
            'a1'             => 'starting up',
            'a2'             => 'hobos.with.god@gmail.com',
            'a3'             => 'YES',
            'fb_id'          => '3102027389891885',
        ];

        $requestData = [
            'facebook_groups' => [
                'fb_id'   => $facebookGroup->fb_id,
                'fb_name' => $facebookGroup->fb_name,
            ],
            'group_members'   => [$groupMembers],
            'file_name' => '782292072290590_GROUPKIT__Asia_Kolkata__1616484791.csv',
        ];

        $this->mock(GroupMembers::class)
            ->shouldReceive('upsert')
            ->withSomeOfArgs(['user_id', 'fb_id', 'group_id']);

        $expectedTags = [explode(',', $groupMembers['tags'])];

        $tagServiceMock = $this->mock(TagService::class);

        $tagServiceMock->shouldReceive('bulkStoreOrUpdate')
            ->withSomeOfArgs(
                $expectedTags,
                $facebookGroup->id,
                $this->user->id,
            );

        $response = $this->json('POST', route('groups.importCsv', $requestData));

        $response->assertOk();
        $response->assertJsonStructure(['code', 'message', 'data']);
        $response->assertJsonFragment([
            'code'    => Response::HTTP_OK,
            'message' => "Successfully imported data!\nUpdates may take a moment to be reflected in your dashboard.",
            'data'    => [],
        ]);

        $this->assertDatabaseHas('facebook_groups', [
            'id'         => $facebookGroup->id,
            'fb_id'      => $facebookGroup->fb_id,
            'fb_name'    => $facebookGroup->fb_name,
            'deleted_at' => null,
        ]);
    }

    /**
     * @test
     * that addMembers returns limit message response if user cant add more groups
     *
     * @covers ::addMembers
     *
     * @dataProvider addMembers_withGroupSoftDeleteFlagsProvider
     *
     * @param bool $groupSoftDeleted flag to show that group is soft deleted with requested group id
     */
    public function addMembers_withGroupSoftDeleteFlags_returnsLimitMessage(bool $groupSoftDeleted)
    {
        $userMock = $this->getMockBuilder(User::class)
            ->onlyMethods(['canAddAnother'])
            ->setProxyTarget($this->user)
            ->disableOriginalConstructor()
            ->getMock();
        $this->actingAs($userMock);
        $userMock->expects(static::once())->method('canAddAnother')->with('group')->willReturn(false);

        $requestGroupId = 3232;
        if ($groupSoftDeleted) {
            FacebookGroups::factory()->create([
                'user_id'    => $this->user->id,
                'fb_id'      => $requestGroupId,
                'deleted_at' => now()->subDay(),
            ]);
        }

        $response = $this->postJson(
            route('groups.addMembers'),
            ['group' => ['groupid' => $requestGroupId], 'members' => [['user_id' => 3232]]]
        );

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
        $response->assertJsonStructure(['message']);
        $response->assertJsonFragment([
            'message' => 'Your plan\'s group limit has been reached. ' . $this->upgradePlanLink(),
        ]);
        $this->assertDatabaseMissing('facebook_groups', [
            'fb_id'      => $requestGroupId,
            'user_id'    => $this->user->id,
            'deleted_at' => null,
        ]);
    }

    /**
     * Data provider for {@see addMembers_withGroupSoftDeleteFlags_returnsLimitMessage}
     *
     * @return array containing group soft deleted flag
     */
    public function addMembers_withGroupSoftDeleteFlagsProvider()
    {
        return [
            ['groupSoftDeleted' => false],
            ['groupSoftDeleted' => true],
        ];
    }

    /**
     * @test
     * that addMembers returns success import message if group members are added
     *
     * @covers ::addMembers
     */
    public function addMembers_withNewGroup_returnsImportSuccessMessage()
    {
        $userMock = $this->getMockBuilder(User::class)
            ->onlyMethods(['canAddAnother'])
            ->setProxyTarget($this->user)
            ->disableOriginalConstructor()
            ->getMock();
        $this->actingAs($userMock);
        $userMock->setAttribute('id', $this->user->id);
        $userMock->expects(static::once())->method('canAddAnother')->with('group')->willReturn(true);

        $requestData = [
            'members' => [
                [
                    'user_id'        => 3214323121,
                    'f_name'         => 'John',
                    'l_name'         => 'Doe',
                    'img'            => '',
                    'respond_status' => 'N/A'
                ],
                [
                    'user_id'        => 3214323122,
                    'f_name'         => 'Jane',
                    'l_name'         => 'Doe',
                    'img'            => '',
                    'respond_status' => 'Processing'
                ],
            ],
            'group'   => [
                'groupid'   => 3123123123,
                'groupname' => 'GroupKit',
                'img'       => ''
            ]
        ];

        $this->mock(GroupMembers::class)
            ->shouldReceive('upsert')
            ->withSomeOfArgs(['user_id', 'fb_id', 'group_id'], ['deleted_at', 'img']);

        $response = $this->postJson(route('groups.addMembers'), $requestData);

        $response->assertOk();
        $response->assertJsonStructure(['message']);
        $response->assertJsonFragment(['message' => 'Successfully inserted']);
    }

    /**
     * @test
     * that addMembers returns {@see Response::HTTP_BAD_REQUEST} when an exception has thrown.
     *
     * @covers ::addMembers
     */
    public function addMembers_whenThrowsAnException_returnsHTTPBadRequest()
    {
        $userMock = $this->getMockBuilder(User::class)
            ->onlyMethods(['canAddAnother'])
            ->setProxyTarget($this->user)
            ->disableOriginalConstructor()
            ->getMock();
        $this->actingAs($userMock);
        $userMock->setAttribute('id', $this->user->id);
        $userMock->expects(static::once())->method('canAddAnother')->with('group')->willReturn(true);

        $requestData = [
            'members' => [
                [
                    'user_id'        => 3214323121,
                    'f_name'         => 'John',
                    'l_name'         => 'Doe',
                    'img'            => '',
                ]
            ],
            'group'   => [
                'groupid'   => 3123123123,
                'groupname' => 'GroupKit',
                'img'       => ''
            ]
        ];

        $this->mock(GroupMembers::class)
            ->shouldReceive('upsert')
            ->withSomeOfArgs(['user_id', 'fb_id', 'group_id'], ['deleted_at'])
            ->andThrow(new Exception());

        $response = $this->postJson(route('groups.addMembers'), $requestData);

        $response->assertStatus(Response::HTTP_BAD_REQUEST);
        $response->assertJsonStructure(['message']);
        $response->assertJsonFragment(['message' => 'Something went wrong']);
    }

    /**
     * @test
     * that importGroupMembersTags throws an exception if the array of group members is not equal to
     * the number of tags
     *
     * @covers ::importGroupMembersTags
     */
    public function importGroupMembersTags_whenNumberOfGroupMembersAreNotEqualToTags_throwsAnException()
    {
        $currentMock = $this->createMock(GroupController::class);
        $tags = Tag::factory(5)->make();
        $facebookGroup = FacebookGroups::factory()->create();
        $groupMembers = GroupMembers::factory(30)->create(['group_id' => $facebookGroup->id]);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Number of the tags are not equal to the number of the group members');
        $this->expectExceptionCode(Response::HTTP_UNPROCESSABLE_ENTITY);

        TestHelper::callNonPublicFunction(
            $currentMock,
            'importGroupMembersTags',
            [$tags->toArray(), $groupMembers->toArray(), $facebookGroup]
        );
    }

    /**
     * @test
     * that setColumnsVisibility returns HTTP {@see Response::HTTP_BAD_REQUEST} code with proper message
     * when validation fails for the tested method
     *
     * @covers ::setColumnsVisibility
     *
     * @dataProvider setColumnsVisibility_withVariousMissingOrWrongPropertiesProvider
     *
     * @param array $setColumnsVisibilityRequest including request parameters that will be sent to the tested method
     * @param string $expectedMessage of the tested method call
     */
    public function setColumnsVisibility_withVariousMissingOrWrongProperties_returnsBadHTTPCode(
        array $setColumnsVisibilityRequest,
        string $expectedMessage
    ) {
        FacebookGroups::factory(['id' => static::FACEBOOK_GROUP_ID])->create();

        $response = $this->post(route('groups.setColumnsVisibility'), $setColumnsVisibilityRequest);

        $response->assertStatus(Response::HTTP_BAD_REQUEST);
        $response->assertJsonStructure(['message', 'data']);
        $response->assertJsonFragment([
            'message' => $expectedMessage,
            'data' => [],
        ]);
    }

    /**
     * Data provider for {@see setColumnsVisibility_withVariousMissingOrWrongProperties_returnsBadHTTPCode}
     *
     * @return array[] including request data and expected message of the tested method call
     */
    public function setColumnsVisibility_withVariousMissingOrWrongPropertiesProvider(): array
    {
        return [
            'Group Id Is Required Field' => [
                'setColumnsVisibilityRequest' => [
                    'columnsVisibility' => static::COLUMNS_VISIBILITY,
                ],
                'expectedMessage' => 'The group id field is required.',
            ],
            'Group Id Must Be An Integer' => [
                'setColumnsVisibilityRequest' => [
                    'groupId' => 'three',
                    'columnsVisibility' => static::COLUMNS_VISIBILITY,
                ],
                'expectedMessage' => 'The group id must be an integer.',
            ],
            'Group Id Must Be In The Database' => [
                'setColumnsVisibilityRequest' => [
                    'groupId' => 2,
                    'columnsVisibility' => static::COLUMNS_VISIBILITY,
                ],
                'expectedMessage' => 'The selected group id is invalid.',
            ],
             'Columns Visibility Must Be An Array' => [
                'setColumnsVisibilityRequest' => [
                    'columnsVisibility' => 111,
                    'groupId' => static::FACEBOOK_GROUP_ID,
                ],
                'expectedMessage' => 'The columns visibility must be an array.',
            ],
        ];
    }

    /**
     * @test
     * that setColumnsVisibility inserts columns visibility into database and returns success response
     *
     * @covers ::setColumnsVisibility
     */
    public function setColumnsVisibility_whenPassesValidation_insertsColumnsVisibility()
    {
        $facebookGroup = FacebookGroups::factory()->create(['user_id' => $this->user->id]);

        $this->assertDatabaseMissing('group_settings', [
            'user_id' => $this->user->id,
            'group_id' => $facebookGroup->id,
            'columns_visibility' => json_encode(static::COLUMNS_VISIBILITY),
        ]);

        $response = $this->post(route('groups.setColumnsVisibility'), [
            'groupId' => $facebookGroup->id,
            'columnsVisibility' => static::COLUMNS_VISIBILITY,
        ]);

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonStructure(['message']);
        $response->assertJsonFragment(['message' => 'Columns visibility state stored successfully.']);
        $this->assertDatabaseHas('group_settings', [
            'user_id' => $this->user->id,
            'group_id' => $facebookGroup->id,
            'columns_visibility' => json_encode(static::COLUMNS_VISIBILITY),
        ]);
    }

    /**
     * @test
     * that getColumnsVisibility returns HTTP {@see Response::HTTP_NOT_FOUND} status code
     * when provided Facebook group doesn't exist in the database
     *
     * @covers ::getColumnsVisibility
     */
    public function getColumnsVisibility_whenFacebookGroupDoesNotExistInDatabase_returnsHTTPNotFoundStatus()
    {
        $facebookGroupId = 11;

        $response = $this->get(route('groups.getColumnsVisibility', $facebookGroupId));

        $response->assertStatus(Response::HTTP_NOT_FOUND);
    }

    /**
     * @test
     * that getColumnsVisibility returns empty data when provided Facebook group doesn't belong to the logged-in user
     *
     * @covers ::getColumnsVisibility
     */
    public function getColumnsVisibility_whenProvidedGroupDoesNotBelongToTheSessionUser_returnsNullableData()
    {
        $facebookGroup = FacebookGroups::factory()->create();

        $response = $this->get(route('groups.getColumnsVisibility', $facebookGroup->id));

        $response->assertJsonStructure(['data']);
        $response->assertJsonFragment(['data' => null]);
        $response->assertStatus(Response::HTTP_OK);
    }

    /**
     * @test
     * that getColumnsVisibility returns columns visibility from the database
     * for the logged-in user and his provided Facebook group
     *
     * @covers ::getColumnsVisibility
     */
    public function getColumnsVisibility_whenProvidedGroupBelongToTheSessionUser_returnsColumnsVisibility()
    {
        $facebookGroup = FacebookGroups::factory(['user_id' => $this->user->id])->create();
        $this->user->groupColumnsSettings()
            ->sync([
                $facebookGroup->id => ['columns_visibility' => json_encode(static::COLUMNS_VISIBILITY)],
            ]);

        $response = $this->get(route('groups.getColumnsVisibility', $facebookGroup->id));

        $this->assertEquals(
            json_encode(static::COLUMNS_VISIBILITY),
            $response->getData()->data->columns_visibility->columns_visibility
        );
        $response->assertJsonStructure(['data']);
        $response->assertStatus(Response::HTTP_OK);
    }

    /**
     * @test
     * that covers the next cases:
     * 1. assures that all members from the group will be returned, when there
     * is no excludedMemberIds.
     * 2. assures that the excluded member won't be present in the collection.
     * 3. assures that deleted members are not present in the collection.
     * 4. assures that it will be returned only members whose group_add_time
     *    is greater or equal to startDate.
     * 5. assures that it will be returned only members whose group_add_time
     *    is lesser than endDate.
     * 6. assures that it will be returned only members whose group_add_time
     *    is lesser than endDate.
     * 7. assures that it will be returned only members whose first and last name,
     *    fb_id or email are like search term.
     * 8. assures that it will be returned only members that are associated with
     *    the appropriate tags.
     *
     * @dataProvider getMembersNames_withVariousRequestsProvider
     *
     * @covers ::getMembersNames
     *
     * @param array $getMembersNamesRequest including request parameters that will be sent to the tested method.
     * @param int $numberOfPresentMembers number of the users that must be present in the response.
     * @param bool $softDelete true if test user is soft-deleted, otherwise false.
     *
     * @return void
     */
    public function getMembersNames_withVariousRequests_returnsOnlySelectedMembersFromTheGroup(
        array $getMembersNamesRequest,
        int $numberOfPresentMembers,
        bool $softDelete = false
    ): void {
        $this->addMySQLConcatFunction();

        $facebookGroup = FacebookGroups::factory(['id' => static::FACEBOOK_GROUP_ID])->create();
        GroupMembers::factory(3)->create(['group_id' => $facebookGroup->id]);

        $testMember = GroupMembers::factory()->create([
            'group_id' => $facebookGroup->id,
            'f_name' => 'Michael',
            'l_name' => 'Jordan',
            'deleted_at' => $softDelete ? now() : null,
        ]);
        $testMemberPresentInTheResponse = 0;

        $tag = Tag::factory()->create(['group_id' => $facebookGroup->id, 'label' => 'TEST_TAG']);
        $testMember->tags()->sync(
            [
                1 => [
                    'group_member_id' => $testMember->id,
                    'tag_id' => $tag->id,
                    'group_id' => $facebookGroup->id,
                ],
            ],
        );

        $members = GroupMembers::get(['id', 'fb_id', 'f_name', 'l_name'])->map(function ($member) {
            return [
                'full_name' => "{$member->f_name} {$member->l_name}",
                'fb_id' => $member->fb_id,
                'id' => $member->id,
            ];
        });

        if ($getMembersNamesRequest['excluded_member_ids']) {
            $members = $members->filter(function ($member) use ($getMembersNamesRequest) {
                return !in_array($member['id'], $getMembersNamesRequest['excluded_member_ids']);
            });
        }

        $response = $this->post(route('groups.getMembersNames'), $getMembersNamesRequest);

        foreach ($response->getData()->members as $member) {
            $this->assertContains((array) $member, $members);
            $testMemberPresentInTheResponse++;
        }
        $response->assertOk();
        $response->assertJsonStructure(['members', 'fbGroupId']);
        $this->assertCount($numberOfPresentMembers, $response->getData()->members);
        $response->assertJsonCount($numberOfPresentMembers, 'members');
        $this->assertEquals($numberOfPresentMembers, $testMemberPresentInTheResponse);
    }

    /**
     * Data provider for {@see getMembersNames_withVariousRequests_returnsOnlySelectedMembersFromTheGroup}.
     *
     * @return array[] including request data and expected message of the tested method call.
     */
    public function getMembersNames_withVariousRequestsProvider()
    {
        return [
            'There Is No Excluded Members' => [
                'getMembersNamesRequest' => [
                    'excluded_member_ids' => [],
                    'group_id' => static::FACEBOOK_GROUP_ID,
                    'is_multi_page_select_all' => true,
                ],
                'numberOfPresentMembers' => 4,
            ],
            'The Test Member Is Excluded' => [
                'getMembersNamesRequest' => [
                    'excluded_member_ids' => [4],
                    'group_id' => static::FACEBOOK_GROUP_ID,
                    'is_multi_page_select_all' => true,
                ],
                'numberOfPresentMembers' => 3,
            ],
            'The Response Data Do Not Contain Deleted Members' => [
                'getMembersNamesRequest' => [
                    'excluded_member_ids' => [],
                    'group_id' => static::FACEBOOK_GROUP_ID,
                    'is_multi_page_select_all' => true,
                ],
                'numberOfPresentMembers' => 3,
                'softDelete' => true,
            ],
            'Members whose startDate is greater than the date_add_time date' => [
                'getMembersNamesRequest' => [
                    'excluded_member_ids' => [],
                    'group_id' => static::FACEBOOK_GROUP_ID,
                    'is_multi_page_select_all' => true,
                    'startDate' => now()->addDay(),
                ],
                'numberOfPresentMembers' => 0,
            ],
            'Members whose startDate is equal to than the date_add_time date' => [
                'getMembersNamesRequest' => [
                    'excluded_member_ids' => [-1],
                    'group_id' => static::FACEBOOK_GROUP_ID,
                    'is_multi_page_select_all' => true,
                    'startDate' => now(),
                ],
                'numberOfPresentMembers' => 4,
            ],
            'Members whose startDate is lesser than the date_add_time date' => [
                'getMembersNamesRequest' => [
                    'excluded_member_ids' => [-1],
                    'group_id' => static::FACEBOOK_GROUP_ID,
                    'is_multi_page_select_all' => true,
                    'startDate' => now()->subDay(),
                ],
                'numberOfPresentMembers' => 4,
            ],
            'Members whose endDate is greater than the date_add_time date' => [
                'getMembersNamesRequest' => [
                    'excluded_member_ids' => [],
                    'group_id' => static::FACEBOOK_GROUP_ID,
                    'is_multi_page_select_all' => true,
                    'endDate' => now()->addDay(),
                ],
                'numberOfPresentMembers' => 4,
            ],
            'Members whose endDate is lesser than the date_add_time date' => [
                'getMembersNamesRequest' => [
                    'excluded_member_ids' => [],
                    'group_id' => static::FACEBOOK_GROUP_ID,
                    'is_multi_page_select_all' => true,
                    'endDate' => now()->subDay(),
                ],
                'numberOfPresentMembers' => 0,
            ],
            'Members that have tags associated to their account' => [
                'getMembersNamesRequest' => [
                    'excluded_member_ids' => [-1],
                    'group_id' => static::FACEBOOK_GROUP_ID,
                    'is_multi_page_select_all' => true,
                    'tags' => '1',
                ],
                'numberOfPresentMembers' => 1,
            ],
            'Members that have NO tags associated to their account' => [
                'getMembersNamesRequest' => [
                    'excluded_member_ids' => [-1],
                    'group_id' => static::FACEBOOK_GROUP_ID,
                    'is_multi_page_select_all' => true,
                    'tags' => '2',
                ],
                'numberOfPresentMembers' => 0,
            ],
        ];
    }

    /**
     * @test
     * that setColumnsWidth returns {@see Response::HTTP_BAD_REQUEST} code with proper message
     * when validation fails for the tested method
     *
     * @covers ::setColumnsWidth
     *
     * @dataProvider setColumnsWidth_withVariousMissingOrWrongPropertiesProvider
     *
     * @param array $setColumnsWidthRequest including request parameters that will be sent to the tested method
     * @param string $expectedMessage of the tested method call
     */
    public function setColumnsWidth_withVariousMissingOrWrongProperties_returnsBadHTTPCode(
        array $setColumnsWidthRequest,
        string $expectedMessage
    ) {
        FacebookGroups::factory(['id' => static::FACEBOOK_GROUP_ID])->create();

        $response = $this->post(route('groups.setGroupColumnsWidth'), $setColumnsWidthRequest);

        $response->assertStatus(Response::HTTP_BAD_REQUEST);
        $response->assertJsonStructure(['message', 'data']);
        $response->assertJsonFragment([
            'message' => $expectedMessage,
            'data' => [],
        ]);
    }

    /**
     * Data provider for {@see setColumnsWidth_withVariousMissingOrWrongProperties_returnsBadHTTPCode}
     *
     * @return array[] including request data and expected message of the tested method call
     */
    public function setColumnsWidth_withVariousMissingOrWrongPropertiesProvider(): array
    {
        return [
            'Group Id Is Required Field' => [
                'setColumnsWidthRequest' => [
                    'columnsWidth' => static::COLUMNS_WIDTH,
                ],
                'expectedMessage' => 'The group id field is required.',
            ],
            'Group Id Must Be An Integer' => [
                'setColumnsWidthRequest' => [
                    'groupId' => 'three',
                    'columnsWidth' => static::COLUMNS_WIDTH,
                ],
                'expectedMessage' => 'The group id must be an integer.',
            ],
             'Columns Width Must Be An Array' => [
                'setColumnsWidthRequest' => [
                    'columnsWidth' => 111,
                    'groupId' => static::FACEBOOK_GROUP_ID,
                ],
                'expectedMessage' => 'The columns width must be an array.',
            ],
        ];
    }

    /**
     * @test
     * that getMembersNames returns HTTP {@see Response::HTTP_BAD_REQUEST} code with proper message
     * when validation fails for the tested method
     *
     * @covers ::getMembersNames
     *
     * @dataProvider getMembersNames_withVariousMissingOrWrongPropertiesProvider
     *
     * @param array $getMembersNamesRequest including request parameters that will be sent to the tested method
     * @param string $expectedMessage of the tested method call
     *
     * @return void
     */
    public function getMembersNames_withVariousMissingOrWrongProperties_returnsBadHTTPCode(
        array $getMembersNamesRequest,
        string $expectedMessage
    ): void {
        FacebookGroups::factory(['id' => static::FACEBOOK_GROUP_ID])->create();

        $response = $this->post(route('groups.getMembersNames'), $getMembersNamesRequest);

        $response->assertStatus(Response::HTTP_BAD_REQUEST);
        $response->assertJsonStructure(['data']);
        $response->assertJsonFragment([
            'message' => $expectedMessage,
            'data' => [],
        ]);
    }

    /**
     * Data provider for {@see getMembersNames_withVariousMissingOrWrongProperties_returnsBadHTTPCode}
     *
     * @return array[] including request data and expected message of the tested method call
     */
    public function getMembersNames_withVariousMissingOrWrongPropertiesProvider(): array
    {
        return [
            'Group Id Is Required Field' => [
                'getMembersNamesRequest' => [
                    'excluded_member_ids' => [],
                    'is_multi_page_select_all' => true,
                ],
                'expectedMessage' => 'The group id field is required.',
            ],
            'Group Id Must Be An Integer' => [
                'getMembersNamesRequest' => [
                    'group_id' => 'three',
                    'excluded_member_ids' => [],
                    'is_multi_page_select_all' => true,
                ],
                'expectedMessage' => 'The group id must be an integer.',
            ],
            'Group Id Must Be In The Database' => [
                'getMembersNamesRequest' => [
                    'group_id' => 2,
                    'excluded_member_ids' => [],
                    'is_multi_page_select_all' => true,
                ],
                'expectedMessage' => 'The selected group id is invalid.',
            ],
             'Excluded Members IDs must be present, even if it is an empty array' => [
                'getMembersNamesRequest' => [
                    'group_id' => static::FACEBOOK_GROUP_ID,
                    'is_multi_page_select_all' => true,
                ],
                'expectedMessage' => 'The excluded member ids field must be present.',
            ],
            'Selected Members IDs must be present, if is_multi_page_select_all is false' => [
                'getMembersNamesRequest' => [
                    'group_id' => 1,
                    'excluded_member_ids' => [],
                    'is_multi_page_select_all' => false,
                ],
                'expectedMessage' =>
                    'The selected member ids field is required when is multi page select all is false.',
            ],
        ];
    }

    /**
     * @test
     * that setColumnsWidth returns {@see Response::HTTP_UNAUTHORIZED} status code
     * when authenticated user provides group without access
     *
     * @covers ::setColumnsWidth
     */
    public function setColumnsWidth_whenGroupDoesNotBelowToUser_returnsHTTPUnathorized()
    {
        $unauthenticatedUser = User::factory()->create();
        $facebookGroup = FacebookGroups::factory()->create(['user_id' => $unauthenticatedUser->id]);

        $this->assertAuthenticatedAs($this->user);

        $response = $this->post(route('groups.setGroupColumnsWidth'), [
            'groupId' => $facebookGroup->id,
            'columnsWidth' => static::COLUMNS_WIDTH,
        ]);

        $response->assertStatus(Response::HTTP_UNAUTHORIZED);
        $response->assertJsonStructure(['message']);
        $response->assertJsonFragment(['message' => 'You do not have access to set columns width for provided group']);
    }

    /**
     * @test
     * that setColumnsWidth inserts columns visibility into database and returns success response
     *
     * @covers ::setColumnsWidth
     */
    public function setColumnsWidth_happyPath_insertsColumnsVisibility()
    {
        $facebookGroup = FacebookGroups::factory()->create(['user_id' => $this->user->id]);
        $this->user->groupSettings()->attach([
            $facebookGroup->id => [
                'columns_visibility' => json_encode(static::COLUMNS_VISIBILITY),
            ],
        ]);

        $response = $this->post(route('groups.setGroupColumnsWidth'), [
            'groupId' => $facebookGroup->id,
            'columnsWidth' => static::COLUMNS_WIDTH,
        ]);

        $response->assertOk();
        $response->assertJsonStructure(['message']);
        $response->assertJsonFragment(['message' => 'Columns width stored successfully.']);
        $this->assertDatabaseHas('group_settings', [
            'user_id' => $this->user->id,
            'group_id' => $facebookGroup->id,
            'columns_width' => json_encode(static::COLUMNS_WIDTH),
            'columns_visibility' => json_encode(static::COLUMNS_VISIBILITY),
        ]);
    }

    /**
     * @test
     * that getGroupSettings returns empty data when provided Facebook group doesn't belong to the logged-in user
     *
     * @covers ::getGroupSettings
     */
    public function getGroupSettings_whenProvidedGroupDoesNotBelongToTheUser_returnsNull()
    {
        $facebookGroup = FacebookGroups::factory()->create();

        $response = $this->get(route('groups.getGroupSettings', $facebookGroup->id));

        $response->assertJsonStructure(['group_settings']);
        $response->assertJsonFragment(['group_settings' => null]);
        $response->assertOk();
    }

    /**
     * @test
     * that getGroupSettings returns HTTP {@see Response::HTTP_NOT_FOUND} status code
     * when provided Facebook group doesn't exist in the database
     *
     * @covers ::getGroupSettings
     */
    public function getGroupSettings_whenFacebookGroupDoesNotExistInDatabase_returnsHTTPNotFoundStatus()
    {
        $facebookGroupId = 11;

        $response = $this->get(route('groups.getGroupSettings', $facebookGroupId));

        $response->assertStatus(Response::HTTP_NOT_FOUND);
    }

    /**
     * @test
     * that getGroupSettings returns columns visibility, columns width from the database
     * for the logged-in user and his provided Facebook group
     *
     * @covers ::getGroupSettings
     */
    public function getGroupSettings_whenProvidedGroupBelongToTheSessionUser_returnsColumnsVisibility()
    {
        $facebookGroup = FacebookGroups::factory(['user_id' => $this->user->id])->create();
        $this->user->groupColumnsSettings()
            ->sync([
                $facebookGroup->id => [
                    'columns_visibility' => json_encode(static::COLUMNS_VISIBILITY),
                    'columns_width' => json_encode(static::COLUMNS_WIDTH)
                ],
            ]);

        $response = $this->get(route('groups.getGroupSettings', $facebookGroup->id));

        $response->assertJsonFragment([
            'id' => $facebookGroup->id,
            'fb_id' => (string)$facebookGroup->fb_id,
            'fb_name' => $facebookGroup->fb_name,
            'img' => $facebookGroup->img,
            'user_id' => (string)$facebookGroup->user_id,
            'columns_visibility' => json_encode(static::COLUMNS_VISIBILITY),
            'columns_width' => json_encode(static::COLUMNS_WIDTH),
        ]);
        $response->assertJsonStructure(['group_settings']);
        $response->assertOk();
    }
}
