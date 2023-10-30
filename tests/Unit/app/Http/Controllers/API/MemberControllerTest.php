<?php

namespace Tests\Unit\app\Http\Controllers\API;

use App\Exceptions\Integrations\GroupLimitExceededException;
use App\FacebookGroups;
use App\GroupMembers;
use App\Http\Controllers\API\MemberController;
use App\Http\Middleware\SendIntegration;
use App\Services\TagService;
use App\Tag;
use App\User;
use Carbon\Carbon;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Laravel\Passport\Passport;
use ReflectionException;
use Symfony\Component\HttpFoundation\Response;
use App\Exceptions\Integrations\NoMembersToSendException;
use Tests\TestCase;
use Tests\TestHelper;

/**
 * Class MemberControllerTest adds test coverage for {@see \App\Http\Controllers\Api\MemberController} class
 *
 * @package Tests\Unit\app\Http\Controllers\API
 * @coversDefaultClass \App\Http\Controllers\Api\MemberController
 */
class MemberControllerTest extends TestCase
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
        $this->artisan('passport:install');

        $this->assertGuest();
    }

    /**
     * @test
     * that init sets {@see SendIntegration} middleware
     *
     * @covers ::init
     *
     * @throws ReflectionException if init method is not defined
     */
    public function init_always_setsMiddleware()
    {
        $currentMock = $this->getMockBuilder(MemberController::class)
            ->onlyMethods(['middleware'])
            ->addMethods(['only'])
            ->disableOriginalConstructor()
            ->getMock();

        $currentMock->expects(static::once())->method('middleware')->with('send.integration')->willReturnSelf();
        $currentMock->expects(static::once())->method('only');

        TestHelper::callNonPublicFunction($currentMock, 'init');
    }

    /**
     * @test
     * that index:
     * 1. Doesn't apply filters to the group members when there are no filters in request
     * 2. Returns all group members for the provided facebook group id
     *
     * @covers ::index
     */
    public function index_withoutFilters_returnsAllGroupMembers()
    {
        $this->actingAsApiUser();
        $facebookGroup = FacebookGroups::factory()->create();
        $members = GroupMembers::factory(20)->create(['group_id' => $facebookGroup->id]);

        $response = $this->json('POST', route('member', [
            'group_id' => $facebookGroup->id,
        ]));

        $response->assertOk();
        $response->assertJsonStructure(['code', 'data']);
        $response->assertJsonFragment(['code' => Response::HTTP_OK]);
        $this->assertCount(
            $members->count(),
            json_decode($response->getContent())->data->group->members
        );
    }

    /**
     * @test
     * that index returns all group members added between the provided start and end date
     * when start and end date params are provided in the request
     *
     * @covers ::index
     */
    public function index_withStartAndEndDateParams_returnsFilteredMembersBetweenStartAndEndDate()
    {
        $this->actingAsApiUser();
        $facebookGroup = FacebookGroups::factory()->create();
        GroupMembers::factory(20)->create([
            'group_id'      => $facebookGroup->id,
            'date_add_time' => Carbon::now(),
        ]);
        $expectedMembers = GroupMembers::factory(5)->create([
            'group_id'      => $facebookGroup->id,
            'date_add_time' => Carbon::now()->subDays(3),
        ]);

        $response = $this->json('POST', route('member'), [
            'group_id'  => $facebookGroup->id,
            'startDate' => Carbon::now()->subDays(4),
            'endDate'   => Carbon::now()->subDays(2),
        ]);

        $response->assertOk();
        $response->assertJsonStructure(['code', 'data']);
        $response->assertJsonFragment(['code' => Response::HTTP_OK]);
        $this->assertCount(
            $expectedMembers->count(),
            json_decode($response->getContent())->data->group->members
        );
    }

    /**
     * @test
     * that index returns paginated group members added from yesterday to the current time
     * when start date param is provided in the request
     *
     * @covers ::index
     */
    public function index_withStartDateParameter_returnsFilteredMembersByStartDate()
    {
        $this->actingAsApiUser();

        $facebookGroup = FacebookGroups::factory()->create();
        $membersAddedYesterday = GroupMembers::factory(20)->create([
            'group_id' => $facebookGroup->id,
            'date_add_time' => now()->subDay(),
        ]);
        $membersAddedToday = GroupMembers::factory(20)->create([
            'group_id' => $facebookGroup->id,
            'date_add_time' => now(),
        ]);

        GroupMembers::factory(5)->create([
            'group_id' => $facebookGroup->id,
            'date_add_time' => now()->subDays(3),
        ]);

        $response = $this->json('POST', route('member'), [
            'group_id' => $facebookGroup->id,
            'startDate' => now()->subDay()->toDateString(),
            'perPage' => 10,
            'page' => 1,
        ]);

        $response->assertOk();
        $response->assertJsonStructure(['code', 'data']);
        $response->assertJsonFragment([
            'code' => Response::HTTP_OK,
            'members_found' => $membersAddedYesterday->count() + $membersAddedToday->count(),
            'id' => $facebookGroup->id,
            'fb_id' => (string)$facebookGroup->fb_id,
            'fb_name' => $facebookGroup->fb_name,
        ]);
    }

    /**
     * @test
     * that index returns paginated all group members that are added yesterday and before yesterday
     * when end date param is provided in the request
     *
     * @covers ::index
     */
    public function index_withEndDateParameter_returnsFilteredMembersByEndDate() {
        $this->actingAsApiUser();
        $facebookGroup = FacebookGroups::factory()->create();
        $membersAddedFiveDaysAgo = GroupMembers::factory(20)->create([
            'group_id' => $facebookGroup->id,
            'date_add_time' => now()->subDays(5),
        ]);
        $membersAddedYesterday = GroupMembers::factory(20)->create([
            'group_id' => $facebookGroup->id,
            'date_add_time' => now()->subDay(),
        ]);

        GroupMembers::factory(5)->create([
            'group_id' => $facebookGroup->id,
            'date_add_time' => now(),
        ]);

        $response = $this->json('POST', route('member'), [
            'group_id' => $facebookGroup->id,
            'endDate' => now()->subDay()->toDateString(),
            'perPage' => 10,
            'page' => 1,
        ]);

        $response->assertOk();
        $response->assertJsonStructure(['code', 'data']);
        $response->assertJsonFragment([
            'code' => Response::HTTP_OK,
            'members_found' => $membersAddedFiveDaysAgo->count() + $membersAddedYesterday->count(),
            'id' => $facebookGroup->id,
            'fb_id' => (string)$facebookGroup->fb_id,
            'fb_name' => $facebookGroup->fb_name,
        ]);
    }

    /**
     * Setup method for {@see index_whenTagsAreProvided_returnsFilteredMembersByTags}
     *
     * @return array containing:
     * 1. Facebook group created in the database
     * 2. Tags that will be used in a request
     * 3. Group Members that should exist in the response
     */
    private function index_whenTagsAreProvidedSetUp(): array
    {
        $facebookGroup = FacebookGroups::factory()->create();
        $requestedTags = Tag::factory(5)->create(['group_id' => $facebookGroup->id]);
        Tag::factory(10)->create(['group_id' => $facebookGroup->id]);

        $groupMembers = GroupMembers::factory(20)->create([
            'group_id'      => $facebookGroup->id,
            'date_add_time' => Carbon::now(),
        ]);
        $expectedMembers = GroupMembers::factory(5)->create([
            'group_id'      => $facebookGroup->id,
            'date_add_time' => Carbon::now()->subDays(3),
        ]);

        $groupMembersTags = [];
        foreach ($groupMembers as $groupMember) {
            $groupMembersTags[] = [
                'group_id'        => $facebookGroup->id,
                'tag_id'          => $requestedTags[0]->id,
                'group_member_id' => $groupMember->id,
            ];
            $groupMembersTags[] = [
                'group_id'        => $facebookGroup->id,
                'tag_id'          => $requestedTags[1]->id,
                'group_member_id' => $groupMember->id,
            ];

        }

        foreach ($expectedMembers as $groupMember) {
            foreach ($requestedTags as $tag) {
                $groupMembersTags[] = [
                    'group_id' => $facebookGroup->id,
                    'tag_id' => $tag->id,
                    'group_member_id' => $groupMember->id,
                ];
            }
        }
        DB::table('group_members_tags')->insert($groupMembersTags);

        return [$facebookGroup, $requestedTags, $expectedMembers];
    }

    /**
     * @test
     * that index returns only group members that contain all provided tags
     * when tags id are provided in the request
     *
     * @covers ::index
     */
    public function index_whenTagsAreProvided_returnsFilteredMembersByTags()
    {
        $this->actingAsApiUser();
        [
            $facebookGroup,
            $requestedTags,
            $expectedMembers,
        ] = $this->index_whenTagsAreProvidedSetUp();

        $requestedTagsId = implode(',', $requestedTags->pluck('id')->toArray());
        $response = $this->json('POST', route('member'), [
            'group_id' => $facebookGroup->id,
            'tags' => $requestedTagsId,
        ]);

        $response->assertOk();
        $response->assertJsonStructure(['code', 'data']);
        $response->assertJsonFragment([
            'code' => Response::HTTP_OK,
            'members_found' => $expectedMembers->count(),
        ]);

        foreach ($expectedMembers as $expectedMember) {
            $response->assertJsonFragment([
                'id' => $expectedMember->id,
                'f_name' => $expectedMember->f_name,
                'l_name' => $expectedMember->l_name,
            ]);
        }

        foreach ($requestedTags as $requestedTag) {
            $response->assertJsonFragment([
                'id' => $requestedTag->id,
                'label' => $requestedTag->label,
            ]);
        }
    }

    /**
     * @test
     * that index returns group members which have the provided auto responder statuses
     * if auto responder param is provided in the request
     *
     * @covers ::index
     *
     * @dataProvider index_withVariousAutoResponderStatusesProvider
     *
     * @param string $responderStatus represents the status of the autoresponder that is requested
     */
    public function index_withVariousAutoResponderStatuses_returnsFilteredMembersByAutoResponderStatus(
        string $responderStatus
    ) {
        $this->actingAsApiUser();
        $facebookGroup = FacebookGroups::factory()->create();
        $expectedMembers = GroupMembers::factory(4)->create([
            'group_id' => $facebookGroup->id,
            'date_add_time' => Carbon::now(),
            'respond_status' => GroupMembers::RESPONSE_STATUSES[$responderStatus],
        ]);
        foreach (GroupMembers::RESPONSE_STATUSES as $responderKey => $responderValue) {
            if ($responderStatus !== $responderKey) {
                GroupMembers::factory(10)->create([
                    'group_id' => $facebookGroup->id,
                    'date_add_time' => Carbon::now()->subDays(3),
                    'respond_status' => $responderValue,
                ]);
            }
        }

        $response = $this->json('POST', route('member'), [
            'group_id'      => $facebookGroup->id,
            'autoResponder' => $responderStatus,
        ]);

        $response->assertOk();
        $response->assertJsonStructure(['code', 'data']);
        $response->assertJsonFragment(['code' => Response::HTTP_OK]);
        $this->assertCount(
            $expectedMembers->count(),
            json_decode($response->getContent())->data->group->members
        );
    }

    /**
     * Data provider for {@see index_withVariousAutoResponderStatuses_returnsFilteredMembersByAutoResponderStatus}
     *
     * @return array[] containing responder status
     */
    public function index_withVariousAutoResponderStatusesProvider(): array
    {
        $responseStatuses = array_diff(
            GroupMembers::RESPONSE_STATUSES,
            [GroupMembers::RESPONSE_STATUS_ERROR => GroupMembers::RESPONSE_STATUSES['ERROR']]
        );

        return array_map(function ($responderStatus) {
            return ['responderStatus' => $responderStatus];
        }, array_keys($responseStatuses));
    }

    /**
     * @test
     * that index returns group members filtered by full name if search param is provided
     *
     * @covers ::index
     */
    public function index_whenSearchIsProvided_returnsFilteredMembersByFullName()
    {
        $this->addMySQLConcatFunction();
        $user = $this->actingAsApiUser();
        $facebookGroup = FacebookGroups::factory()->create();
        GroupMembers::factory(20)->create(['group_id' => $facebookGroup->id]);

        $expectedFirstMember = GroupMembers::factory()->create([
            'group_id' => $facebookGroup->id,
            'date_add_time' => now(),
            'f_name' => 'Sasa',
            'l_name' => 'Djordjevic',
        ]);

        $expectedSecondMember = GroupMembers::factory()->create([
            'group_id' => $facebookGroup->id,
            'user_id' => $user->id,
            'date_add_time' => now(),
            'f_name' => 'Sasa',
            'l_name' => 'Djurdjevic',
        ]);

        $searchTerm = 'Sasa Dj';
        $response = $this->json('POST', route('member'), [
            'group_id' => $facebookGroup->id,
            'perPage' => 10,
            'searchText' => $searchTerm,
            'page' => 1,
        ]);

        $response->assertOk();
        $response->assertJsonStructure(['code', 'data']);
        $response->assertJsonFragment([
            'code' => Response::HTTP_OK,
            'members_found' => 2,
            'id' => $facebookGroup->id,
            'fb_id' => (string)$facebookGroup->fb_id,
            'fb_name' => $facebookGroup->fb_name,
        ]);

        $response->assertJsonFragment([
            'id' => $expectedFirstMember->id,
            'f_name' => $expectedFirstMember->f_name,
            'fb_id' => (string)$expectedFirstMember->fb_id,
            'l_name' => $expectedFirstMember->l_name,
        ]);

        $response->assertJsonFragment([
            'id' => $expectedSecondMember->id,
            'f_name' => $expectedSecondMember->f_name,
            'fb_id' => (string)$expectedSecondMember->fb_id,
            'l_name' => $expectedSecondMember->l_name,
        ]);
    }

    /**
     * @test
     * that index return group members sorted by field from $sortName value in a direction of $sortOrder value
     *
     * @covers ::index
     *
     * @dataProvider index_withVariousSortParamsProvider
     *
     * @param string $sortName represents the field in the database by which will be group members sorted
     * @param string $sortOrder represents direction for sorting by,
     *                          it can be asc as ascending or desc as descending
     */
    public function index_withVariousSortParams_returnsSortedGroupMembers(
        string $sortName,
        string $sortOrder
    ) {
        $this->actingAsApiUser();
        $facebookGroup = FacebookGroups::factory()->create();
        $groupMembers = GroupMembers::factory(5)->create([
            'group_id' => $facebookGroup->id,
            'date_add_time' => now(),
        ]);

        $response = $this->json('POST', route('member'), [
            'group_id' => $facebookGroup->id,
            'sort' => [
                'sortName' => $sortName,
                'sortOrder' => $sortOrder,
            ],
            'perPage' => 10,
            'page' => 1,
        ]);

        $response->assertOk();
        $response->assertJsonStructure(['code', 'data']);
        $response->assertJsonFragment([
            'code' => Response::HTTP_OK,
            'members_found' => $groupMembers->count(),
            'id' => $facebookGroup->id,
            'fb_id' => (string)$facebookGroup->fb_id,
            'fb_name' => $facebookGroup->fb_name,
        ]);

        $expectedMembers = GroupMembers::orderBy($sortName, $sortOrder)->get();
        $membersInResponse = json_decode($response->getContent())->data->group->members;
        $membersInResponseCount = count($membersInResponse);
        for ($i = 0; $i < $membersInResponseCount; $i++) {
            $this->assertEquals(
                [
                    'id' => $expectedMembers[$i]->id,
                    'f_name' => $expectedMembers[$i]->f_name,
                    'l_name' => $expectedMembers[$i]->l_name,
                    'email' => $expectedMembers[$i]->email,
                    'fb_id' => $expectedMembers[$i]->fb_id,
                    'notes' => $expectedMembers[$i]->notes,
                    'date_add_time' => $expectedMembers[$i]->date_add_time,
                    'respond_status' => $expectedMembers[$i]->respond_status,
                    'a1' => $expectedMembers[$i]->a1,
                    'a2' => $expectedMembers[$i]->a2,
                    'a3' => $expectedMembers[$i]->a3,
                    'group_id' => $expectedMembers[$i]->group_id,
                ],
                [
                    'id' => $membersInResponse[$i]->id,
                    'f_name' => $membersInResponse[$i]->f_name,
                    'l_name' => $membersInResponse[$i]->l_name,
                    'email' => $membersInResponse[$i]->email,
                    'fb_id' => $membersInResponse[$i]->fb_id,
                    'notes' => $membersInResponse[$i]->notes,
                    'date_add_time' => $membersInResponse[$i]->date_add_time,
                    'respond_status' => $membersInResponse[$i]->respond_status,
                    'a1' => $membersInResponse[$i]->a1,
                    'a2' => $membersInResponse[$i]->a2,
                    'a3' => $membersInResponse[$i]->a3,
                    'group_id' => $membersInResponse[$i]->group_id,
                ]
            );
        }
    }

    /**
     * Data provider for {@see index_withVariousSortParams_returnsSortedGroupMembers}
     *
     * @return string[][] containing sort name field and sort order (desc and asc)
     */
    public function index_withVariousSortParamsProvider()
    {
        return [
            ['sortName' => 'f_name', 'sortOrder' => 'desc'],
            ['sortName' => 'f_name', 'sortOrder' => 'asc'],
            ['sortName' => 'date_add_time', 'sortOrder' => 'desc'],
            ['sortName' => 'date_add_time', 'sortOrder' => 'asc'],
            ['sortName' => 'email', 'sortOrder' => 'desc'],
            ['sortName' => 'email', 'sortOrder' => 'asc'],
            ['sortName' => 'respond_status', 'sortOrder' => 'desc'],
            ['sortName' => 'respond_status', 'sortOrder' => 'asc'],
            ['sortName' => 'a1', 'sortOrder' => 'desc'],
            ['sortName' => 'a1', 'sortOrder' => 'asc'],
            ['sortName' => 'a2', 'sortOrder' => 'desc'],
            ['sortName' => 'a2', 'sortOrder' => 'asc'],
            ['sortName' => 'a3', 'sortOrder' => 'desc'],
            ['sortName' => 'a3', 'sortOrder' => 'asc'],
            ['sortName' => 'notes', 'sortOrder' => 'desc'],
            ['sortName' => 'notes', 'sortOrder' => 'asc'],
        ];
    }

    /**
     * @test
     * that getGroupsTag returns all tags assigned to the group provided in the request
     *
     * @covers ::getGroupsTag
     */
    public function getGroupsTag_always_returnsTags()
    {
        $this->actingAsApiUser();
        $facebookGroup = FacebookGroups::factory()->create();
        $tags = Tag::factory(5)->create(['group_id' => $facebookGroup->id]);
        $tags->each(function ($tag) use ($facebookGroup) {
            $groupMember = GroupMembers::factory()->create(['group_id' => $facebookGroup->id]);
            DB::table('group_members_tags')->insert(
                [
                    'tag_id' => $tag->id,
                    'group_id' => $facebookGroup->id,
                    'group_member_id' => $groupMember->id,
                ]
            );
        });

        $response = $this->json('GET', route('getGroupsTag', $facebookGroup->id));

        $this->assertAuthenticated();

        $response->assertOk();
        $response->assertJsonStructure(['code', 'data']);
        $response->assertJsonFragment(['code' => Response::HTTP_OK]);

        foreach ($tags as $tag) {
            $response->assertJsonFragment([
               'id' => $tag->id,
               'label' => $tag->label,
               'group_id' => (string)$facebookGroup->id,
            ]);
        }
    }

    /**
     * @test
     * that methods returns "unauthorized" error HTTP response if the authenticated user is not authorized
     * to update the requested group member
     *
     * @covers ::update
     *
     * @dataProvider restrictedMethods_whenUserCantAccessTheGroupProvider
     *
     * @param string $requestType of the tested route
     * @param string $url of the tested route
     * @param array $requestParams for the API call
     * @param string $expectedMessage of the tested method call
     *
     * @todo after {@see \App\Http\Controllers\API\MemberController::update} refactor put code from this method
     * to the below method
     */
    public function restrictedMethods_whenUserCantAccessTheGroup_returnUnauthorizedHTTPResponse(
        string $requestType,
        string $url,
        array $requestParams,
        string $expectedMessage
    ) {
        FacebookGroups::factory()->create(['id' => $requestParams['group_id']]);
        $user = $this->actingAsApiUser();
        $userMock = $this->getMockBuilder(User::class)
            ->onlyMethods(['canAccessGroup'])
            ->setProxyTarget($user)
            ->disableOriginalConstructor()
            ->getMock();

        Passport::actingAs($userMock);

        $userMock->expects(static::once())
            ->method('canAccessGroup')
            ->with($requestParams['group_id'])
            ->willReturn(false);

        $this->app->instance('User', $userMock);

        $response = $this->json($requestType, route($url, $requestParams));

        $this->assertAuthenticated();
        $response->assertOk();
        $response->assertJsonStructure(['code', 'message']);
        $response->assertJsonFragment(['code' => Response::HTTP_UNAUTHORIZED]);
        $response->assertJsonFragment(['message' => $expectedMessage]);
    }

    /**
     * Data provider for {@see restrictedMethods_whenUserCantAccessTheGroup_returnUnauthorizedHTTPResponse}
     *
     * @return array[] containing request type, url, request parameters and expected message
     */
    public function restrictedMethods_whenUserCantAccessTheGroupProvider(): array
    {
        return [
            [
                'requestType' => 'POST',
                'url' => 'memberUpdate',
                'requestParams' => [
                    'id'       => 32,
                    'group_id' => 1,
                ],
                'expectedMessage' => 'You do not have an access to this group.',
            ],
        ];
    }

    /**
     * @test
     *
     * that methods returns "unauthorized" error HTTP response if the authenticated user is not authorized
     * to update the requested group member
     *
     * @covers ::removeGroupMembers
     *
     * @dataProvider restrictedMethod_whenUserCantAccessTheGroupProvider
     *
     * @param string $requestType of the tested route
     * @param string $url of the tested route
     * @param array $requestParams for the API call
     * @param int $expectedCode of the tested method call
     * @param string $expectedMessage of the tested method call
     */
    public function restrictedMethod_whenUserCantAccessTheGroup_returnUnauthorizedHTTPResponse(
        string $requestType,
        string $url,
        array $requestParams,
        int $expectedCode,
        string $expectedMessage
    ) {
        FacebookGroups::factory()->create(['id' => $requestParams['group_id']]);
        $user = $this->actingAsApiUser();
        $userMock = $this->getMockBuilder(User::class)
            ->onlyMethods(['canAccessGroup'])
            ->setProxyTarget($user)
            ->disableOriginalConstructor()
            ->getMock();

        Passport::actingAs($userMock);

        $userMock->expects(static::once())
            ->method('canAccessGroup')
            ->with($requestParams['group_id'])
            ->willReturn(false);

        $this->app->instance('User', $userMock);

        $response = $this->json($requestType, route($url, $requestParams));

        $this->assertAuthenticated();
        $response->status($expectedCode);
        $response->assertJsonStructure(['message']);
        $response->assertJsonFragment(['message' => $expectedMessage]);
    }

    /**
     * Data provider for {@see restrictedMethods_whenUserCantAccessTheGroup_returnUnauthorizedHTTPResponse}
     *
     * @return array[] containing request type, url, code, request parameters and expected message
     */
    public function restrictedMethod_whenUserCantAccessTheGroupProvider(): array
    {
        return [
            [
                'requestType' => 'POST',
                'url' => 'removeGroupMembers',
                'requestParams' => [
                    'group_id' => 1,
                    'selected_member_ids' => [1,2],
                    'is_multi_page_select_all' => false,
                ],
                'code' => Response::HTTP_UNAUTHORIZED,
                'expectedMessage' => 'You do not have access to delete these group members',
            ],
        ];
    }

    /**
     * @test
     * that update returns HTTP Not Found {@see Response::HTTP_NOT_FOUND} response when the group member is not found
     *
     * @covers ::update
     */
    public function update_whenGroupMemberIsNotFound_returnsHTTPNotFoundResponse()
    {
        $user = $this->actingAsApiUser();
        $userMock = $this->getMockBuilder(User::class)
            ->onlyMethods(['canAccessGroup'])
            ->setProxyTarget($user)
            ->disableOriginalConstructor()
            ->getMock();
        $userMock->setAttribute('id', $user->id);
        Passport::actingAs($userMock);
        $groupId = 3;
        $userMock->expects(static::once())->method('canAccessGroup')->with($groupId)->willReturn(true);
        $this->app->instance('User', $userMock);

        $response = $this->json('POST', route('memberUpdate', [
            'id'       => 32,
            'group_id' => $groupId,
        ]));

        $this->assertAuthenticated();
        $response->assertOk();
        $response->assertJsonStructure(['code', 'message']);
        $response->assertJsonFragment(['code' => Response::HTTP_NOT_FOUND]);
        $response->assertJsonFragment(['message' => 'Record Not Found.']);
    }

    /**
     * @test
     * that update returns HTTP Internal Server Error
     * {@see Response::HTTP_INTERNAL_SERVER_ERROR} if an exception is thrown
     *
     * @covers ::update
     */
    public function update_whenAnExceptionIsThrown_returnsHTTPInternalServerErrorResponse()
    {
        $user = $this->actingAsApiUser();
        $userMock = $this->getMockBuilder(User::class)
            ->onlyMethods(['canAccessGroup'])
            ->setProxyTarget($user)
            ->disableOriginalConstructor()
            ->getMock();
        $userMock->setAttribute('timezone', null);
        Passport::actingAs($userMock);

        $groupMember = GroupMembers::factory()->create();
        $userMock->expects(static::once())->method('canAccessGroup')->with($groupMember->group_id)->willReturn(true);

        $requestData = [
            'id'            => $groupMember->id,
            'group_id'      => $groupMember->group_id,
            'email'         => 'john.doe@gmail.com',
            'date_add_time' => now()->format('m-d-Y G:i:s'),
            'tags_to_delete' => [],
            'tags_to_add' => [],
        ];

        $groupMemberMock = $this->mock(GroupMembers::class);
        $groupMemberMock
            ->shouldReceive('where')
            ->with('group_id', $groupMember->id)
            ->andReturnSelf();
        $groupMemberMock
            ->shouldReceive('find')
            ->with($groupMember->id)
            ->andReturnSelf();
        $groupMemberMock->shouldReceive('setAttribute')
            ->andReturn(Carbon::createFromFormat('m-d-Y G:i:s', $requestData['date_add_time']));

        $groupMemberMock->shouldReceive('getAttribute')
            ->with('group_id')
            ->andReturns($groupMember->group_id);

        $groupMemberMock->shouldReceive('getAttribute')
            ->with('id')
            ->andReturns($groupMember->id);

        $groupMemberMock
            ->shouldReceive('fill')
            ->with(Arr::except($requestData, ['date_add_time', 'tags_to_delete', 'tags_to_add']))
            ->andReturnSelf();

        $tagServiceMock = $this->mock(TagService::class);
        $tagServiceMock->shouldReceive('manageTags') #there is no provided tags for add/delete
            ->with(
                [],
                $groupMember->group_id,
                [$groupMember->id]
            );

        $exceptionMessage = 'Something went wrong';
        $groupMemberMock
            ->shouldReceive('update')
            ->andThrow(new Exception($exceptionMessage));

        $groupMemberMock
            ->shouldReceive('toArray')
            ->andReturn($groupMember->toArray());

        $this->app->instance('User', $userMock);
        $this->app->instance('GroupMembers', $groupMemberMock);
        $this->app->instance('TagService', $tagServiceMock);

        $response = $this->json('POST', route('memberUpdate', $requestData));

        $this->assertAuthenticated();

        $response->assertOk();
        $response->assertJsonStructure(['code', 'message']);
        $response->assertJsonFragment([
            'code' => Response::HTTP_INTERNAL_SERVER_ERROR,
            'message' => $exceptionMessage,
        ]);

        $this->assertDatabaseMissing('group_members', $requestData);
    }

    /**
     * Setup method for:
     * {@see update_happyPath_updatesGroupMember}
     * {@see update_withTagsToAdd_addTagsToTheGroupMember}
     * {@see update_withTagsToDelete_deletesTagsFromGroupMember}
     *
     * @return array containing:
     * 1. Request data for update group member API
     * 2. Group member that will be updated
     * 3. User mock of the logged user
     * 4. User logged into application
     */
    private function updateSetUp()
    {
        $user = $this->actingAsApiUser();
        $userMock = $this->getMockBuilder(User::class)
            ->onlyMethods(['canAccessGroup'])
            ->setProxyTarget($user)
            ->disableOriginalConstructor()
            ->getMock();

        Passport::actingAs($userMock);

        $groupMember = GroupMembers::factory()->create();

        $requestData = [
            'id'            => $groupMember->id,
            'group_id'      => $groupMember->group_id,
            'email'         => 'john.doe@gmail.com',
            'f_name'        => 'John',
            'l_name'        => 'Doe',
            'date_add_time' => now()->format('m-d-Y G:i:s'),
        ];

        $userMock->expects(static::once())->method('canAccessGroup')->with($groupMember->group_id)->willReturn(true);

        return [$requestData, $groupMember, $userMock, $user];
    }

    /**
     * @test
     * that update:
     * 1. Returns HTTP OK response {@see Response::HTTP_OK} with a successful update message
     * 2. Updates the group member in the database
     * 3. Converts the provided date_add_time member field to the user's timezone
     *
     * @covers ::update
     */
    public function update_happyPath_updatesGroupMember()
    {
        [$requestData, $groupMember, $userMock, $user] = $this->updateSetUp();

        $userMock->setAttribute('timezone', $user->timezone);

        $this->app->instance('User', $userMock);

        $response = $this->json('POST', route('memberUpdate', $requestData));

        $this->assertAuthenticated();

        $response->assertOk();
        $response->assertJsonStructure(['code', 'message']);
        $response->assertJsonFragment([
            'code' => Response::HTTP_OK,
            'message' => 'Update Successfully.',
        ]);

        $this->assertDatabaseHas('group_members', Arr::except($requestData, 'date_add_time'));
        $this->assertDatabaseHas('group_members', [
            'date_add_time' => Carbon::createFromFormat(
                'm-d-Y G:i:s',
                $requestData['date_add_time'],
                $user->timezone
            )->setTimezone(config('app.timezone'))->format('Y-m-d H:i:s')
        ]);
    }

    /**
     * @test
     * that update:
     * 1. Returns HTTP OK response {@see Response::HTTP_OK} with a successful update message
     * 2. Updates the group member in the database
     * 3. Store new tags and connect them to the group member
     * when tags_to_add are provided in the request
     *
     * @covers ::update
     */
    public function update_withTagsToAdd_addTagsToTheGroupMember()
    {
        [$requestData, $groupMember, $userMock] = $this->updateSetUp();
        $tagsToAdd = ['Messaged', 'Customer', 'Answered'];
        $requestData['tags_to_add'] = $tagsToAdd;

        $this->app->instance('User', $userMock);

        $response = $this->json('POST', route('memberUpdate', $requestData));

        $this->assertAuthenticated();

        $response->assertOk();
        $response->assertJsonStructure(['code', 'message']);
        $response->assertJsonFragment([
            'code' => Response::HTTP_OK,
            'message' => 'Update Successfully.',
        ]);

        $this->assertDatabaseHas('group_members', Arr::except($requestData, ['date_add_time', 'tags_to_add']));

        foreach ($tagsToAdd as $tag) {
            $this->assertDatabaseHas('tags', [
               'label' => $tag,
               'group_id' => $groupMember->group_id,
            ]);


            $this->assertDatabaseHas('group_members_tags', [
                'tag_id' => Tag::where('label', $tag)->where('group_id', $groupMember->group_id)->first()->id,
                'group_id' => $groupMember->group_id,
                'group_member_id' => $groupMember->id,
            ]);
        }
    }

    /**
     * @test
     * that update:
     * 1. Returns HTTP OK response {@see Response::HTTP_OK} with a successful update message
     * 2. Updates the group member in the database
     * 3. Deletes tags from the group member
     * when tags_to_delete are provided in the request
     *
     * @covers ::update
     */
    public function update_withTagsToDelete_deletesTagsFromGroupMember()
    {
        [$requestData, $groupMember, $userMock] = $this->updateSetUp();

        $tags = ['Messaged', 'Customer', 'Answered'];
        $groupMembersTags = [];
        $tagsToDelete = [];
        foreach ($tags as $tag) {
            $createdTag = Tag::factory()->create(['group_id' => $groupMember->group_id, 'label' => $tag]);
            $tagsToDelete[] = $tag;
            $groupMembersTags[] = [
                'group_member_id' => $groupMember->id,
                'group_id' => $groupMember->group_id,
                'tag_id' => $createdTag->id,
            ];
        }
        DB::table('group_members_tags')->insert($groupMembersTags);

        $requestData['tags_to_delete'] = $tagsToDelete;

        $this->app->instance('User', $userMock);

        $response = $this->json('POST', route('memberUpdate', $requestData));

        $this->assertAuthenticated();

        $response->assertOk();
        $response->assertJsonStructure(['code', 'message']);
        $response->assertJsonFragment([
            'code' => Response::HTTP_OK,
            'message' => 'Update Successfully.',
        ]);

        $this->assertDatabaseHas('group_members', Arr::except($requestData, ['date_add_time', 'tags_to_delete']));

        foreach ($tags as $tag) {
            $this->assertDatabaseMissing('tags', [
               'label' => $tag,
               'group_id' => $groupMember->group_id,
            ]);

            $this->assertDatabaseMissing('group_members_tags', [
                'tag_id' => Tag::where('label', $tag)->where('group_id', $groupMember->group_id)->first() ?Tag::where('label', $tag)->where('group_id', $groupMember->group_id)->first()->id : null,
                'group_id' => $groupMember->group_id,
                'group_member_id' => $groupMember->id,
            ]);
        }
    }

    /**
     * @test
     * that update:
     * 1. Returns HTTP OK response {@see Response::HTTP_OK} with a successful update message
     * 2. Updates the group member in the database
     * 3. Deletes tags_to_delete from the group member
     * 4. Adds tags_to_add to the group member
     * when tags_to_delete and tags_to_add are provided in the request
     *
     * @covers ::update
     */
    public function update_withTagsToDeleteAndAdd_deletesAndAddsTagsFromGroupMember()
    {
        [$requestData, $groupMember, $userMock] = $this->updateSetUp();

        $tags = ['Messaged', 'Customer', 'Answered'];
        $groupMembersTags = [];
        $tagsToDelete = [];
        foreach ($tags as $tag) {
            $createdTag = Tag::factory()->create(['group_id' => $groupMember->group_id, 'label' => $tag]);
            $tagsToDelete[] = $tag;
            $groupMembersTags[] = [
                'group_member_id' => $groupMember->id,
                'group_id' => $groupMember->group_id,
                'tag_id' => $createdTag->id,
            ];
        }
        DB::table('group_members_tags')->insert($groupMembersTags);

        $tagsToAdd = ['Admin', 'Privilege', 'Root Access'];

        $requestData['tags_to_delete'] = $tagsToDelete;
        $requestData['tags_to_add'] = $tagsToAdd;

        $this->app->instance('User', $userMock);

        $response = $this->json('POST', route('memberUpdate', $requestData));

        $this->assertAuthenticated();

        $response->assertOk();
        $response->assertJsonStructure(['code', 'message']);
        $response->assertJsonFragment([
            'code' => Response::HTTP_OK,
            'message' => 'Update Successfully.',
        ]);

        $this->assertDatabaseHas(
            'group_members',
            Arr::except($requestData, ['date_add_time', 'tags_to_delete', 'tags_to_add'])
        );

        foreach ($tags as $tag) {
            $this->assertDatabaseMissing('tags', [
               'label' => $tag,
               'group_id' => $groupMember->group_id,
            ]);

            $testTag = Tag::where('label', $tag)->where('group_id', $groupMember->group_id)->first();
            $this->assertDatabaseMissing('group_members_tags', [
                'tag_id' => $testTag ? $testTag->id : null,
                'group_id' => $groupMember->group_id,
                'group_member_id' => $groupMember->id,
            ]);
        }

        foreach ($tagsToAdd as $tag) {
            $this->assertDatabaseHas('tags', [
                'label' => $tag,
                'group_id' => $groupMember->group_id,
            ]);

            $this->assertDatabaseHas('group_members_tags', [
                'tag_id' => Tag::where('label', $tag)->where('group_id', $groupMember->group_id)->first()->id,
                'group_id' => $groupMember->group_id,
                'group_member_id' => $groupMember->id,
            ]);
        }
    }

    /**
     * @test
     * that removeGroupMembers:
     * 1. Removes GroupMembers from the database {@see SoftDeletes}
     * 2. Returns HTTP Ok Response {@see Response::HTTP_OK} with message that contains the number of deleted members
     *
     * @covers ::removeGroupMembers
     */
    public function removeGroupMembers_happyPath_removesMembers()
    {
        $user = $this->actingAsApiUser();
        $userMock = $this->getMockBuilder(User::class)
            ->onlyMethods(['canAccessGroup'])
            ->setProxyTarget($user)
            ->disableOriginalConstructor()
            ->getMock();
        Passport::actingAs($userMock);

        $facebookGroup = FacebookGroups::factory()->create();
        $groupMembers = GroupMembers::factory(5)->create(['group_id' => $facebookGroup->id]);
        $userMock->expects(static::once())->method('canAccessGroup')->with($facebookGroup->id)->willReturn(true);
        $this->app->instance('User', $userMock);

        $requestData = [
            'group_id' => $facebookGroup->id,
            'selected_member_ids' => $groupMembers->pluck('id')->toArray(),
            'is_multi_page_select_all' => false,
        ];

        $response = $this->json('POST', route('removeGroupMembers', $requestData));

        $response->assertOk();
        $response->assertJsonStructure(['message']);
        $response->assertJsonFragment([
            'message' => 'Successfully removed ' . $groupMembers->count() . ' members',
        ]);

        foreach ($groupMembers as $groupMember) {
            $this->assertDatabaseMissing('group_members', [
                'id' => $groupMember->id,
                'email' => $groupMember->email,
                'deleted_at' => null,
            ]);
        }
    }

    /**
     * @test
     * that sendToIntegration returns success message if member info sent to integration API successfully
     *
     * @covers ::sendToIntegration
     */
    public function sendToIntegration_withValidRequest_returnsSuccessResponse()
    {
        $user = $this->actingAsApiUser();

        $facebookGroup = FacebookGroups::factory()->create(['user_id' => $user->id]);
        $groupMembers = GroupMembers::factory(5)->create(
            [
                'user_id' => $facebookGroup->id,
                'group_id' => $user->id,
                'respond_status' => GroupMembers::RESPONSE_STATUSES['ADDED'],
                'deleted_at' => null,
            ]
        );
        $groupMemberId = $groupMembers->pluck('id')->toArray();

        $response = $this->json('POST', route('sendToIntegration', ['group_members_id' => $groupMemberId]));

        $response->assertOk();
        $response->assertJsonStructure(['message']);
        $response->assertJsonFragment(
            [
                'message' => 'Your selected group member were re-sent to your configured integration.',
            ]
        );
    }

    /**
     * @test
     * that sendToIntegration throws @see NoMembersToSendException if provided group members are empty
     *
     * @covers \App\Services\MarketingAutomation\AbstractMarketingService::validateBeforeSubscribeAll
     * @covers ::sendToIntegration
     */
    public function sendToIntegration_withEmptyGroupMembers_throwsNoMembersToSendException()
    {
        $this->actingAsApiUser();

        $requestData = ['group_members_id' => []];

        $this->expectException(NoMembersToSendException::class);

        $this->json('POST', route('sendToIntegration', $requestData));
    }

    /**
     * @test
     * that sendToIntegration throws @see GroupLimitExceededException if provided group members
     * bellongs to the group number more than is supported
     *
     * @covers \App\Services\MarketingAutomation\AbstractMarketingService::validateBeforeSubscribeAll
     * @covers ::sendToIntegration
     */
    public function sendToIntegration_withGroupsOverTheLimit_throwsNoMembersToSendException()
    {
        $this->actingAsApiUser();
        $firstGroup = FacebookGroups::factory()->create();
        $secondGroup = FacebookGroups::factory()->create();
        $firstGroupMembers = GroupMembers::factory(5)->create(['group_id' => $firstGroup->id]);
        $secondGroupMembers = GroupMembers::factory(10)->create(['group_id' => $secondGroup->id]);

        $requestData = [
            'group_members_id' => array_merge(
                $firstGroupMembers->pluck('id')->toArray(),
                $secondGroupMembers->pluck('id')->toArray()
            )
        ];

        $this->expectException(GroupLimitExceededException::class);

        $this->json('POST', route('sendToIntegration', $requestData));
    }

    /**
     * @test
     * that index returns group members filtered by email if search param is provided
     *
     * @covers ::index
     */
    public function index_whenSearchIsProvided_returnsFilteredMembersByEmail()
    {
        $this->addMySQLConcatFunction();
        $this->actingAsApiUser();
        $facebookGroup = FacebookGroups::factory()->create();

        $expectedFirstMember = GroupMembers::factory()->create([
            'group_id' => $facebookGroup->id,
            'date_add_time' => now(),
            'email' => 'folami@subira.com',
        ]);

        $notExpectedMember = GroupMembers::factory()->create([
            'group_id' => $facebookGroup->id,
            'date_add_time' => now(),
            'email' => 'follammi@subira.com',
        ]);

        $searchTerm = 'folami@subira.com';
        $response = $this->json('POST', route('member'), [
            'group_id' => $facebookGroup->id,
            'perPage' => 10,
            'searchText' => $searchTerm,
            'page' => 1,
        ]);

        $response->assertOk();
        $response->assertJsonStructure(['code', 'data']);
        $response->assertJsonFragment([
            'code' => Response::HTTP_OK,
            'members_found' => 1,
            'id' => $facebookGroup->id,
            'fb_id' => (string)$facebookGroup->fb_id,
            'fb_name' => $facebookGroup->fb_name,
        ]);

        $response->assertJsonFragment([
            'id' => $expectedFirstMember->id,
            'email' => $expectedFirstMember->email,
            'fb_id' => (string)$expectedFirstMember->fb_id,
        ]);
        $response->assertJsonMissing([
            'id' => $notExpectedMember->id,
            'email' => $notExpectedMember->email,
            'fb_id' => (string)$notExpectedMember->fb_id,
        ]);
    }

    /**
     * @test
     * that index returns all group members containing any of the errors
     * in {@see \App\GroupMembers::RESPONSE_STATUSES} when autoResponder value equals to `ERROR`
     *
     * @covers ::index
     */
    public function index_withAutoResponderError_returnsGroupMembersWithAnyOfTheErrors()
    {
        $this->actingAsApiUser();
        $facebookGroup = FacebookGroups::factory()->create();
        $errorRespondStatuses = array_filter(
            GroupMembers::RESPONSE_STATUSES,
            function ($responseStatus) {
                return !in_array($responseStatus, GroupMembers::$integrationFilterStatuses);
            }
        );
        $expectedMembersInResponse = [];
        foreach ($errorRespondStatuses as $errorRespondStatus) {
            $expectedMembersInResponse[] = GroupMembers::factory()->create([
                'group_id' => $facebookGroup->id,
                'respond_status' => $errorRespondStatus,
            ]);
        }
        foreach (GroupMembers::$integrationFilterStatuses as $groupMembersResponseStatus) {
            GroupMembers::factory(5)->create([
                'group_id' => $facebookGroup->id,
                'respond_status' => $groupMembersResponseStatus,
            ]);
        }

        $response = $this->json('POST', route('member'), [
            'group_id' => $facebookGroup->id,
            'perPage' => 10,
            'page' => 1,
            'autoResponder' => GroupMembers::RESPONSE_STATUS_ERROR,
        ]);

        $this->assertEquals(count($expectedMembersInResponse), $response->json('data.members_found'));
        foreach (GroupMembers::$integrationFilterStatuses as $groupMembersResponseStatus) {
            $response->assertJsonMissing(['respond_status' => $groupMembersResponseStatus]);
        }
    }
}
