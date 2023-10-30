<?php

namespace Tests\Unit\app\Http\Controllers\API;

use App\AutoResponder;
use App\FacebookGroups;
use App\GroupMembers;
use App\Http\Middleware\SaveGroupInfo;
use App\OwnerToTeamMember;
use App\Plan;
use App\TeamMemberGroupAccess;
use App\Traits\GroupTrait;
use App\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Passport\Passport;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;
use App\Jobs\AddMembers;
use Illuminate\Support\Facades\Bus;
use App\Subscriptions;

/**
 * Class ScrapingControllerTest adds test coverage for {@see ScrapingController}
 *
 * @package Tests\Unit\app\Http\Controllers\API
 * @coversDefaultClass \App\Http\Controllers\Api\ScrapingController
 */
class ScrapingControllerTest extends TestCase
{
    use DatabaseMigrations;
    use DatabaseTransactions;
    use GroupTrait;

    /**
     * @var array containing {@see FacebookGroups} request data
     */
    private const REQUEST_FACEBOOK_GROUP = [
        'fb_id' => 397346344819318,
        'fb_name' => 423423423311,
    ];

    /**
     * @var array containing {@see GroupMembers} request data
     */
    private const FACEBOOK_GROUP_MEMBER = [
        'fb_id'          => self::REQUEST_FACEBOOK_GROUP['fb_id'],
        'f_name'         => 'Marko',
        'l_name'         => 'Kraljevic',
        'img'            => '',
        'email'          => '-',
        'user_id'        => 423423423312,
        'a1'             => '',
        'a2'             => '',
        'a3'             => '',
        'respond_status' => 'N/A',
        'date_add_time'  => 1615228078,
    ];

    /**
     * @var array containing chrome extension header to indicate that the request originates from the chrome extension
     */
    private const CHROME_EXTENSION_HEADER = [
        'origin' => 'chrome-extension://?id=pblenjbopecfgkmkmkepboncibolhabk',
    ];

    /**
     * @test
     * that the original action will be prevented from executing if the request validation fails and a JSON response
     * containing the validation message will be returned
     *
     * @covers ::init
     *
     * @dataProvider init_withVariousRoutesProvider
     *
     * @param string $requestType of the tested route
     * @param string $uri of the tested route
     * @param array $requestData containing key value pair params
     * @param string $expectedMessage contained in the response JSON
     */
    public function init_withVariousRoutes_returnsValidationErrorMessage(
        string $requestType,
        string $uri,
        array $requestData,
        string $expectedMessage
    ) {
        $this->actingAsApiUser();

        $response = $this->call($requestType, "/api/{$uri}", $requestData);

        $response->assertStatus(Response::HTTP_BAD_REQUEST);
        $response->assertJsonStructure(['message', 'data']);
        $response->assertJsonFragment([
            'message' => $expectedMessage,
            'data'    => [],
        ]);
    }

    /**
     * Data provider for {@see init_withVariousRoutes_returnsValidationErrorMessage}
     *
     * @return array[] containing route request type, uri of the route, request data
     * and expected message of the tested method call
     */
    public function init_withVariousRoutesProvider()
    {
        return [
            # Validation test cases for saveAutoresponder method
            'Group id is required for saveAutoresponder method' => [
                'requestType'     => 'POST',
                'uri'             => 'saveAutoresponder',
                'requestData'     => [
                    'responder_type' => 'Aweber',
                ],
                'expectedMessage' => 'The group id field is required.',
            ],
            'Responder type is required for saveAutoresponder method' => [
                'requestType'     => 'POST',
                'uri'             => 'saveAutoresponder',
                'requestData'     => [
                    'group_id' => 3,
                ],
                'expectedMessage' => 'The responder type field is required.',
            ],
            # Validation test cases for deleteAutoresponder method
            'Group id is required for deleteAutoresponder method' => [
                'requestType'     => 'POST',
                'uri'             => 'deleteAutoresponder',
                'requestData'     => [],
                'expectedMessage' => 'The group id field is required.',
            ],
        ];
    }

    /**
     * @test
     * that index returns the current user's information without the subscription data if he's not subscribed
     *
     * @covers ::index
     */
    public function index_withoutUserSubscription_returnsUserData()
    {
        $this->actingAsApiUser();

        $response = $this->get(route('getUser'));

        $response->assertOk();
        $response->assertJsonStructure(['code', 'data']);
        $response->assertJsonFragment(['code' => Response::HTTP_OK]);
        $response->assertJsonFragment(['access_team' => false]);
        $response->assertJsonFragment(['can_have_team' => false]);
    }

    /**
     * @test
     * that index returns the current user's information with can have time value from the plan
     *
     * @covers ::index
     */
    public function index_withUserSubscription_returnsUserData()
    {
        $user = User::factory()->create([
            'name'      => 'John Doe',
            'email'     => 'john.doe@gmail.com',
            'password'  => 'password',
            'stripe_id' => 'cus_JDmxtoNHKH1tLx',
        ]);

        Passport::actingAs($user);
        $userClass = $this->mock(User::class);
        $subscriptionStub = new \stdClass();
        $subscriptionStub->stripe_id = $user->stripe_id;
        $subscriptionStub->stripe_plan = Plan::STRIPE_PLAN_IDS['default']['PRO_MONTHLY'];
        $userClass->shouldReceive('getSubscriptionDetails')
            ->with($user->stripe_id)
            ->andReturn($subscriptionStub);

        $response = $this->get(route('getUser'));

        $response->assertOk();
        $response->assertJsonStructure(['code', 'data']);
        $response->assertJsonFragment(['code' => Response::HTTP_OK]);
        $response->assertJsonFragment(['access_team' => false]);
        $response->assertJsonFragment(['can_have_team' => true]);
    }

    /**
     * @test
     * that index returns {@see Response::HTTP_BAD_REQUEST}
     * when retrieving the subscription plan details from Stripe fails
     *
     * @covers ::index
     */
    public function index_whenGetPlanFail_returnsErrorResponse()
    {
        $user = User::factory()->create([
            'name'      => 'John Doe',
            'email'     => 'john.doe@gmail.com',
            'password'  => 'password',
            'stripe_id' => 'cus_JDmxtoNHKH1tLx',
        ]);

        Passport::actingAs($user);
        $userClass = $this->mock(User::class);
        $subscriptionStub = new \stdClass();
        $subscriptionStub->stripe_id = $user->stripe_id;
        $userClass->shouldReceive('getSubscriptionDetails')
            ->with($user->stripe_id)
            ->andReturn($subscriptionStub);

        $response = $this->get(route('getUser'));

        $response->assertOk();
        $response->assertJsonFragment(['code' => Response::HTTP_BAD_REQUEST]);
    }

    /**
     * @test
     * that store returns validation error
     * if the group request data isn't provided in the request
     *
     * @covers ::store
     */
    public function store_withoutGroupRequestData_returnsValidationError()
    {
        $this->actingAsApiUser();
        $this->withoutMiddleware(SaveGroupInfo::class);
        $requestData = [
            'json' => [],
        ];

        $response = $this->postJson(route('recordSave'), $requestData);

        $response->assertOk();
        $response->assertJsonStructure(['code', 'message', 'data']);
        $response->assertJsonFragment([
            'code'    => Response::HTTP_OK,
            'message' => 'The json field is required.',
            'data'    => [],
            'limit'   => true
        ]);
    }

    /**
     * Built structured request from provided values for the store method
     *
     * @param int $userId of the authenticated {@see User}
     * @param array|null $additionalGroupMembers to be included as Facebook group members
     *
     * @return array of formatted request data
     */
    private function storeRequestSetUp(
        int $userId,
        ?array $additionalGroupMembers = null
    ): array {
        $requestData = [
            'user_id' => $userId,
            'json'    => [
                [
                    'group'        => [
                        'groupid'   => self::REQUEST_FACEBOOK_GROUP['fb_id'],
                        'groupname' => self::REQUEST_FACEBOOK_GROUP['fb_name'],
                        'img'       => '',
                    ],
                    'user_details' => [self::FACEBOOK_GROUP_MEMBER],
                ],
            ],
        ];

        if ($additionalGroupMembers) {
            $requestData['json'][0]['user_details'] = array_merge(
                $requestData['json'][0]['user_details'],
                $additionalGroupMembers
            );
        }

        return $requestData;
    }

    /**
     * @test
     * that store adds group and group member data into the database
     *
     * @covers ::store
     */
    public function store_withNewGroup_addsGroupAndGroupMember()
    {
        $user = $this->actingAsApiUser();

        $this->storeSetUp($user);

        $requestData = $this->storeRequestSetUp($user->id);

        $response = $this->postJson(route('recordSave'), $requestData);

        $response->assertOk();
        $response->assertJsonStructure(['code', 'message', 'data']);
        $response->assertJsonFragment([
            'code'    => Response::HTTP_OK,
            'message' => 'Successfully uploaded.',
            'limit' => false,
        ]);

        foreach ($requestData['json'] as $requestJson) {
            $this->assertDatabaseHas('facebook_groups', [
                'fb_id'   => $requestJson['group']['groupid'],
                'fb_name' => $requestJson['group']['groupname'],
            ]);

            foreach ($requestJson['user_details'] as $member) {
                $this->assertDatabaseHas('group_members', [
                    'f_name'      => $member['f_name'],
                    'l_name'      => $member['l_name'],
                    'is_approved' => 0,
                ]);
            }
        }
    }

    /**
     * Mocks user instance for the store method
     *
     * @param User $user instance that will be mocked
     *
     * @return MockObject of the {@see User} class
     */
    private function storeMockSetUp(User $user): MockObject
    {
        $userMock = $this->getMockBuilder(User::class)
            ->setProxyTarget($user)
            ->onlyMethods(
                [
                    'hasSubscription',
                    'groupsOwned',
                    'canAddAnother',
                    'getAvailableCountFor',
                    'activePlan',
                ]
            )
            ->addMethods(
                [
                    'withTrashed',
                    'where',
                    'get',
                ]
            )
            ->getMock();
        $userMock->setAttribute('id', $user->id);
        $userMock->setAttribute('user_id', $user->id);
        $stripeId = 'cus_3123ed3ad123';
        $userMock->setAttribute('stripe_id', $stripeId);
        $userMock->setAttribute('name', $user->name);

        $userMock->expects(static::once())->method('hasSubscription')->with($stripeId)->willReturn(1);
        $userMock->expects(static::once())->method('groupsOwned')->willReturnSelf();
        $userMock->expects(static::once())->method('withTrashed')->willReturnSelf();
        $userMock->expects(static::once())
            ->method('where')
            ->with('fb_id', self::REQUEST_FACEBOOK_GROUP['fb_id'])
            ->willReturnSelf();
        $facebookGroup = FacebookGroups::withTrashed()
            ->where('fb_id', self::REQUEST_FACEBOOK_GROUP['fb_id'])
            ->get();
        $userMock->expects(static::once())->method('get')->willReturn($facebookGroup);
        $facebookGroup[0]->user_id = (int)$user->id;

        return $userMock;
    }

    /**
     * SetUp method for:
     * @see store_withNewGroup_addsGroupAndGroupMember
     * @see store_withTeamMemberAccount_restoresGroupMember
     *
     * @param User $user instance that will be mocked
     *
     * @return void
     */
    private function storeSetUp(User $user): void
    {
        $ownerMock = $this->getMockBuilder(User::class)
            ->setProxyTarget($user)
            ->onlyMethods([
                'activePlan',
                'subscriptionIsPausedForSuspendedService',
                'getAvailableCountFor',
            ])
            ->addMethods(
                [
                    'withTrashed',
                    'where',
                    'get',
                ]
            )
            ->getMock();
        $ownerMock->setAttribute('id', $user->id);
        $ownerMock->setAttribute('name', $user->name);
        $ownerMock->setAttribute('stripe_id', $user->stripe_id);
        $ownerMock->setAttribute('stripe_plan', Plan::STRIPE_PLAN_IDS['default']['PRO_MONTHLY']);
        $ownerMock->expects(static::once())->method('activePlan')->willReturn(true);
        $ownerMock->expects(static::once())->method('getAvailableCountFor')->with('members')->willReturn(1);

        $userMock = $this->partialMock(User::class);
        $userMock->expects()
            ->subscriptionIsPausedForSuspendedService($user->id)
            ->andReturn(false);
        $userMock->expects()->find($user->id)->andReturn($ownerMock);
    }

    /**
     * @test
     * that store adds group members data to the database with is_approved = 1
     * if the request originates from the Chrome extension
     *
     * @covers ::store
     */
    public function store_withExistingGroup_addsGroupMember()
    {
        Bus::fake();

        $this->withoutMiddleware(SaveGroupInfo::class);
        $user = $this->actingAsApiUser();

        $facebookGroupFactory = FacebookGroups::factory()->create([
            'fb_id'   => self::REQUEST_FACEBOOK_GROUP['fb_id'],
            'fb_name' => self::REQUEST_FACEBOOK_GROUP['fb_name'],
            'user_id' => $user->id,
        ]);
        GroupMembers::factory()->create([
            'group_id'   => $facebookGroupFactory->id,
            'user_id'    => $user->id,
            'deleted_at' => now()->subDays(3),
            'fb_id'      => self::FACEBOOK_GROUP_MEMBER['user_id'],
        ]);

        $ownerMock = $this->storeMockSetUp($user);
        $ownerMock->expects(static::once())->method('getAvailableCountFor')->with('members')->willReturn(1);

        $ownerMock->expects(static::once())->method('activePlan')->willReturn(true);

        $userMock = $this->partialMock(User::class);
        $userMock->expects()
            ->subscriptionIsPausedForSuspendedService($user->id)
            ->andReturn(false);
        $userMock->expects()->find($user->id)->andReturn($ownerMock);

        Passport::actingAs($ownerMock);

        $requestData = $this->storeRequestSetUp($user->id);

        $response = $this->postJson(route('recordSave'), $requestData, self::CHROME_EXTENSION_HEADER);

        Bus::assertDispatched(AddMembers::class);

        $response->assertOk();
        $response->assertJsonFragment([
            'message' => 'Successfully uploaded.',
            'code'    => Response::HTTP_OK,
            'limit'   => false,
        ]);
    }

    /**
     * @test
     * that store returns a message suggesting plan upgrade in the response
     * when more groups can't be added due to plan limitation
     *
     * @covers ::store
     *
     * @dataProvider store_withVariousGroupStatesProvider
     *
     * @param bool $existingGroup indicator that facebook group is already stored in the database
     */
    public function store_withVariousGroupStates_returnsUpgradeMessage(bool $existingGroup)
    {
        $this->withoutMiddleware(SaveGroupInfo::class);
        $user = $this->actingAsApiUser();

        $userMock = $this->getMockBuilder(User::class)
            ->setProxyTarget($user)
            ->onlyMethods(['getAvailableCountFor'])
            ->getMock();

        if ($existingGroup) {
            FacebookGroups::factory()->create([
                'fb_id'      => self::REQUEST_FACEBOOK_GROUP['fb_id'],
                'fb_name'    => self::REQUEST_FACEBOOK_GROUP['fb_name'],
                'user_id'    => (int)$user->id,
                'deleted_at' => now()->subDays(3),
            ]);

            $userMock = $this->storeMockSetUp($user);
        }
        $userMock->expects(static::once())->method('getAvailableCountFor')->with('groups')->willReturn(0);

        Passport::actingAs($userMock);

        $requestData = $this->storeRequestSetUp($user->id);

        $response = $this->postJson(route('recordSave'), $requestData);

        $message = 'Your plan\'s group limit has been reached. ' . $this->upgradePlanLink();

        $response->assertOk();
        $response->assertJsonFragment([
            'message' => $message,
            'code'    => Response::HTTP_OK,
            'data'    => [],
            'limit'   => true,
        ]);
        if ($existingGroup) {
            $this->assertSoftDeleted('facebook_groups', [
                'fb_id'   => self::REQUEST_FACEBOOK_GROUP['fb_id'],
                'fb_name' => self::REQUEST_FACEBOOK_GROUP['fb_name'],
            ]);
        }
        $this->assertDatabaseMissing('group_members', [
            'f_name' => $requestData['json'][0]['user_details'][0]['f_name'],
            'l_name' => $requestData['json'][0]['user_details'][0]['l_name'],
            'fb_id'  => $requestData['json'][0]['user_details'][0]['fb_id'],
        ]);
    }

    /**
     * Data provider for {@see store_withVariousGroupStates_returnsUpgradeMessage}
     *
     * @return array[] with existing group is stored in the database indicator
     */
    public function store_withVariousGroupStatesProvider()
    {
        return [
            ['existingGroup' => false],
            ['existingGroup' => true],
        ];
    }

    /**
     * @test
     * that store:
     * 1. returns a message suggesting plan upgrade in the response
     * 2. doesn't store the facebook group members from the request
     * if the current user is unable add requested facebook members from extension due to plan limitation
     *
     * @covers ::store
     */
    public function store_whenCantAddMember_returnsUpgradeMessage()
    {
        $this->withoutMiddleware(SaveGroupInfo::class);
        $user = $this->actingAsApiUser();

        $facebookGroup = FacebookGroups::factory()->create([
            'fb_id'      => self::REQUEST_FACEBOOK_GROUP['fb_id'],
            'fb_name'    => self::REQUEST_FACEBOOK_GROUP['fb_name'],
            'user_id'    => $user->id,
        ]);

        $userMock = $this->storeMockSetUp($user);

        Passport::actingAs($userMock);

        $ownerMock = $this->getMockBuilder(User::class)
            ->setProxyTarget($user)
            ->onlyMethods([
                'getAvailableCountFor',
                'activePlan',
                'subscriptionIsPausedForSuspendedService',
            ])
            ->getMock();
        $ownerMock->setAttribute('id', $user->id);
        $ownerMock->setAttribute('name', $user->name);
        $ownerMock->expects(static::once())->method('getAvailableCountFor')->with('members')->willReturn(0);
        $ownerMock->expects(static::once())->method('activePlan')->willReturn(true);

        $userPartialMock = $this->partialMock(User::class);
        $userPartialMock->expects()
            ->subscriptionIsPausedForSuspendedService($user->id)
            ->andReturn(false);
        $userPartialMock->expects()->find($user->id)->andReturn($ownerMock);

        $additionalRequestGroupMembers = GroupMembers::factory(10)->make([
            'fb_id' => self::REQUEST_FACEBOOK_GROUP['fb_id'],
            'respond_status' => 'Processing',
        ])->toArray();

        $requestData = $this->storeRequestSetUp($user->id, $additionalRequestGroupMembers);

        $response = $this->postJson(route('recordSave'), $requestData, self::CHROME_EXTENSION_HEADER);

        $response->assertOk();
        $response->assertJsonFragment([
            'message' => "[{$user->name}] member limit has been reached. " . $this->upgradePlanLink(),
            'code'    => Response::HTTP_OK,
            'data'    => [],
            'limit'   => true,
        ]);
        $this->assertDatabaseHas('facebook_groups', [
            'fb_id'   => self::REQUEST_FACEBOOK_GROUP['fb_id'],
            'fb_name' => self::REQUEST_FACEBOOK_GROUP['fb_name'],
        ]);
        for ($i = 0; $i < count($requestData['json'][0]['user_details']); $i++) {
            $this->assertDatabaseMissing('group_members', [
                'f_name'   => $requestData['json'][0]['user_details'][$i]['f_name'],
                'l_name'   => $requestData['json'][0]['user_details'][$i]['l_name'],
                'email'    => $requestData['json'][0]['user_details'][$i]['email'],
                'a1'       => $requestData['json'][0]['user_details'][$i]['a1'],
                'a2'       => $requestData['json'][0]['user_details'][$i]['a2'],
                'a3'       => $requestData['json'][0]['user_details'][$i]['a3'],
                'group_id' => $facebookGroup->id,
            ]);
        }
    }

    /**
     * @test
     * that store restores requested group member in the database
     * when the user is authenticated with the team member account
     *
     * @covers ::store
     */
    public function store_withTeamMemberAccount_restoresGroupMember()
    {
        $this->withoutMiddleware(SaveGroupInfo::class);
        $owner = User::factory()->create();
        $teamMember = $this->actingAsApiUser();
        $ownerToTeamMember = OwnerToTeamMember::factory()->create([
            'owner_id'       => $owner->id,
            'team_member_id' => $teamMember->id,
        ]);

        $requestedGroupMemberFacebookId = 312352331312321;

        $facebookGroupFactory = FacebookGroups::factory()->create([
            'fb_id'   => self::REQUEST_FACEBOOK_GROUP['fb_id'],
            'fb_name' => self::REQUEST_FACEBOOK_GROUP['fb_name'],
            'user_id' => $owner->id,
        ]);
        TeamMemberGroupAccess::factory()->create([
            'user_id'                 => $teamMember->id,
            'facebook_group_id'       => $facebookGroupFactory->id,
            'owner_to_team_member_id' => $ownerToTeamMember->id,
        ]);
        GroupMembers::factory()->create([
            'group_id'   => $facebookGroupFactory->id,
            'user_id'    => $teamMember->id,
            'deleted_at' => now()->subDays(3),
            'fb_id'      => $requestedGroupMemberFacebookId,
        ]);

        $this->storeSetUp($owner);

        $requestData = $this->storeRequestSetUp($teamMember->id);

        $response = $this->postJson(route('recordSave'), $requestData, self::CHROME_EXTENSION_HEADER);

        $response->assertOk();
        $response->assertJsonFragment([
            'message' => 'Successfully uploaded.',
            'code'    => Response::HTTP_OK,
            'limit'   => false,
        ]);

        $requestedGroupMember = $requestData['json'][0]['user_details'][0];
        $this->assertDatabaseHas('group_members', [
            'f_name' => $requestedGroupMember['f_name'],
            'l_name' => $requestedGroupMember['l_name'],
            'is_approved' => 1,
            'deleted_at' => null,
            'respond_status' => '',
        ]);
    }

    /**
     * @test
     * that store returns a validation message
     * when provided approved group members contains group member invited by himself
     * and prevents saving provided group members into the database
     *
     * @covers ::validateAddingMembers
     * @covers ::store
     */
    public function store_containingGroupMemberInvitedByTheSameGroupMember_returnsErrorMessage()
    {
        $owner = User::factory()->create();
        Passport::actingAs($owner);

        FacebookGroups::factory()->create([
            'fb_id'   => self::REQUEST_FACEBOOK_GROUP['fb_id'],
            'fb_name' => self::REQUEST_FACEBOOK_GROUP['fb_name'],
            'user_id' => $owner->id,
        ]);

        $this->storeSetUp($owner);

        $facebookGroupMemberWithInvitedByHimSelf = array_merge(
            static::FACEBOOK_GROUP_MEMBER,
            ['invited_by_id' => self::FACEBOOK_GROUP_MEMBER['user_id']],
        );

        $anotherApprovedMember = [
            'fb_id' => self::REQUEST_FACEBOOK_GROUP['fb_id'],
            'f_name' => 'Sasa',
            'l_name' => 'Danilovic',
            'img' => '',
            'email' => '-',
            'user_id' => 923423423312,
            'a1' => '',
            'a2' => '',
            'a3' => '',
            'respond_status' => 'N/A',
            'date_add_time' => 1615298078,
        ];

        $requestData = [
            'user_id' => $owner->id,
            'json' => [
                [
                    'group' => [
                        'groupid' => self::REQUEST_FACEBOOK_GROUP['fb_id'],
                        'groupname' => self::REQUEST_FACEBOOK_GROUP['fb_name'],
                        'img' => '',
                    ],
                    'user_details' => [
                        $facebookGroupMemberWithInvitedByHimSelf,
                        $anotherApprovedMember,
                    ],
                ],
            ],
        ];

        $response = $this->postJson(route('recordSave'), $requestData, self::CHROME_EXTENSION_HEADER);

        $response->assertOk();
        $response->assertJsonFragment([
            'message' => 'The group member cannot invite himself to the group.',
            'code'    => Response::HTTP_OK,
            'limit'   => true,
        ]);

        $requestedGroupMember = $requestData['json'][0]['user_details'][0];
        $this->assertDatabaseMissing('group_members', [
            'f_name' => $requestedGroupMember['f_name'],
            'l_name' => $requestedGroupMember['l_name'],
            'fb_id' => $requestedGroupMember['user_id'],
            'is_approved' => 1,
            'deleted_at' => null,
            'respond_status' => '',
        ]);
        $this->assertDatabaseMissing('group_members', [
            'f_name' => $anotherApprovedMember['f_name'],
            'l_name' => $anotherApprovedMember['l_name'],
            'fb_id' => $anotherApprovedMember['user_id'],
            'is_approved' => 1,
            'deleted_at' => null,
            'respond_status' => '',
        ]);
    }

    /**
     * @test
     * that deleteAutoresponder returns {@see Response::HTTP_BAD_REQUEST} response code
     * if the current user doesn't own the group being deleted
     *
     * @covers ::deleteAutoresponder
     */
    public function deleteAutoresponder_withoutOwnedGroup_returnsInvalidResponse()
    {
        $user = $this->actingAsApiUser();
        $facebookGroupOwnedByUser = FacebookGroups::factory()->create(['user_id' => $user->id]);
        $facebookGroupNotOwnedByThisUser = FacebookGroups::factory()->create();
        $autoResponder = AutoResponder::factory()->create(['group_id' => $facebookGroupNotOwnedByThisUser->id]);
        AutoResponder::factory()->create([
            'user_id'  => $user->id,
            'group_id' => $facebookGroupOwnedByUser->id,
        ]);

        $autoResponderData = [
            'group_id' => $facebookGroupNotOwnedByThisUser->id,
        ];

        $response = $this->postJson(route('deleteAutoresponder', $autoResponderData));

        $response->assertOk();
        $response->assertJsonStructure(['code', 'message', 'data']);
        $response->assertJsonFragment([
            'code'    => Response::HTTP_BAD_REQUEST,
            'message' => 'Invalid Request',
            'data'    => '',
        ]);
        $this->assertDatabaseHas('facebook_groups', [
            'id'      => $facebookGroupNotOwnedByThisUser->id,
            'fb_name' => $facebookGroupNotOwnedByThisUser->fb_name,
        ]);
        $this->assertDatabaseHas('auto_responder', [
            'id' => $autoResponder->id,
        ]);
    }

    /**
     * @test
     * that deleteAutoresponder returns 'Record Not Found' message if the requested auto responder cannot be found
     * for the provided user group
     *
     * @covers ::deleteAutoresponder
     */
    public function deleteAutoresponder_withoutResponder_returnsNotFoundMessage()
    {
        $user = $this->actingAsApiUser();
        $facebookGroupOwnedByUser = FacebookGroups::factory()->create(['user_id' => $user->id]);

        $autoResponderData = [
            'group_id' => $facebookGroupOwnedByUser->id,
        ];

        $response = $this->postJson(route('deleteAutoresponder', $autoResponderData));

        $response->assertOk();
        $response->assertJsonStructure(['code', 'message', 'data']);
        $response->assertJsonFragment([
            'code'    => Response::HTTP_OK,
            'message' => 'Record Not Found.',
            'data'    => '',
        ]);
    }

    /**
     * @test
     * that deleteAutoresponder soft deletes the requested group's auto responder integration
     *
     * @covers ::deleteAutoresponder
     */
    public function deleteAutoresponder_happyPath_deletesAutoResponder()
    {
        $user = $this->actingAsApiUser();
        $facebookGroupOwnedByUser = FacebookGroups::factory()->create(['user_id' => $user->id]);
        $autoResponder = AutoResponder::factory()->create([
            'group_id' => $facebookGroupOwnedByUser->id,
            'user_id' => $user->id,
        ]);

        $autoResponderData = [
            'group_id' => $facebookGroupOwnedByUser->id,
        ];

        $response = $this->postJson(route('deleteAutoresponder', $autoResponderData));

        $response->assertOk();
        $response->assertJsonStructure(['code', 'message', 'data']);
        $response->assertJsonFragment([
            'code'    => Response::HTTP_OK,
            'message' => 'AutoResponder Removed Successfully.',
            'data'    => '',
        ]);
        $this->assertSoftDeleted('auto_responder', [
            'id' => $autoResponder->id,
        ]);
    }

    /**
     * @test
     * that saveAutoresponder updates existing auto responder integration in the database
     *
     * @covers ::saveAutoresponder
     */
    public function saveAutoresponder_withExistingAutoResponder_updatesAutoResponder()
    {
        $user = $this->actingAsApiUser();
        $facebookGroupOwnedByUser = FacebookGroups::factory()->create(['user_id' => $user->id]);
        $autoResponder = AutoResponder::factory()->create([
            'group_id' => $facebookGroupOwnedByUser->id,
            'user_id' => $user->id,
        ]);

        $autoResponderData = [
            'group_id' => $facebookGroupOwnedByUser->id,
            'is_check' => 1,
            'responder_type' => 'Aweber',
        ];

        $response = $this->postJson(route('saveAutoresponder', $autoResponderData));

        $response->assertOk();
        $response->assertJsonStructure(['code', 'message', 'data']);
        $response->assertJsonFragment([
            'code'    => Response::HTTP_OK,
            'message' => "{$autoResponderData['responder_type']} integration saved.",
            'data'    => '',
        ]);
        $this->assertDatabaseHas('auto_responder', [
            'id'             => $autoResponder->id,
            'responder_type' => $autoResponderData['responder_type'],
            'group_id'       => $autoResponderData['group_id'],
        ]);
    }

    /**
     * @test
     * that saveAutoresponder creates new auto responder integration based on data provided in the request
     * if it doesn't already exist for the provided group
     *
     * @covers ::saveAutoresponder
     */
    public function saveAutoresponder_happyPath_storesAutoResponder()
    {
        $user = $this->actingAsApiUser();
        $facebookGroupOwnedByUser = FacebookGroups::factory()->create(['user_id' => $user->id]);

        $autoResponderData = [
            'group_id' => $facebookGroupOwnedByUser->id,
            'is_check' => 1,
            'responder_type' => 'ConvertKit',
        ];

        $response = $this->postJson(route('saveAutoresponder', $autoResponderData));

        $response->assertOk();
        $response->assertJsonStructure(['code', 'message', 'data']);
        $response->assertJsonFragment([
            'code'    => Response::HTTP_OK,
            'message' => "{$autoResponderData['responder_type']} integration saved.",
            'data'    => '',
        ]);
        $this->assertDatabaseHas('auto_responder', [
            'responder_type' => $autoResponderData['responder_type'],
            'group_id'       => $autoResponderData['group_id'],
            'is_check'       => $autoResponderData['is_check'],
        ]);
    }

     /**
     * @test
     * that saveAutoresponder returns {@see Response::HTTP_BAD_REQUEST}
     * if the authenticated user doesn't own the requested group
     *
     * @covers ::saveAutoresponder
     */
    public function saveAutoresponder_withoutAssignedGroup_returnsHTTPBadRequest()
    {
        $this->actingAsApiUser();

        $autoResponderData = [
            'group_id' => 2,
            'is_check' => 1,
            'responder_type' => 'ConvertKit',
        ];

        $response = $this->postJson(route('saveAutoresponder', $autoResponderData));

        $response->assertOk();
        $response->assertJsonStructure(['code', 'message', 'data']);
        $response->assertJsonFragment([
            'code'    => Response::HTTP_BAD_REQUEST,
            'message' => 'Invalid Request',
            'data'    => '',
        ]);
        $this->assertDatabaseMissing('auto_responder', [
            'responder_type' => $autoResponderData['responder_type'],
            'group_id'       => $autoResponderData['group_id'],
            'is_check'       => $autoResponderData['is_check'],
        ]);
    }
}
