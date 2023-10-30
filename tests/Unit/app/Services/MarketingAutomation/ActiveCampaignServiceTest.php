<?php

namespace Tests\Unit\app\Services\MarketingAutomation;

use App\AutoResponder;
use App\Exceptions\Integrations\ActiveCampaign\AuthorizationException;
use App\Exceptions\Integrations\ActiveCampaign\PaymentIssuesException;
use App\Exceptions\Integrations\ActiveCampaign\RateLimitException;
use App\Exceptions\Integrations\ActiveCampaign\RequestUnprocessableException;
use App\Exceptions\Integrations\ActiveCampaign\ResourceNotExistException;
use App\Exceptions\InvalidStateException;
use App\FacebookGroups;
use App\GroupMembers;
use App\Services\MarketingAutomation\AbstractMarketingService;
use App\Services\MarketingAutomation\ActiveCampaignService;
use App\User;
use Bugsnag\BugsnagLaravel\Facades\Bugsnag;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\MockObject\MockObject;
use ReflectionException;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;
use Tests\TestHelper;

/**
 * Class IntegrationServiceTest adds test coverage for {@see ActiveCampaignService}
 *
 * @package Tests\Unit\app\Services\ActiveCampaignService
 * @coversDefaultClass \App\Services\MarketingAutomation\ActiveCampaignService
 */
class ActiveCampaignServiceTest extends TestCase
{
    use DatabaseTransactions;
    use DatabaseMigrations;

    /**
     * Name of the marketing service that will be covered with test cases
     *
     * @var string
     */
    private const SERVICE_NAME = 'ActiveCampaign';

    /**
     * Contact id from active campaign api
     *
     * @var string
     */
    private const CONTACT_ID = '2157';

    /**
     * User logged in the session
     *
     * @var User
     */
    private User $user;

    /**
     * Autoresponder extra parameters, stored in responder_json
     *
     * @var object
     */
    private object $extraParameters;

    /**
     * Contact sync Url to active campaign api
     *
     * @var string
     */
    private string $contactSyncUrl;

    /**
     * Contact list Url to active campaign api
     *
     * @var string
     */
    private string $contactListUrl;

    /**
     * Represents contact tags API url
     *
     * @var string
     */
    private string $addTagsUrl;

    /**
     * Setup test dependencies
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->user = $this->actingAsUser();

        $this->extraParameters = (object)[
            'api_key' => 'test_api_key',
            'host_name' => 'test_host_name',
            'activeList' => (object)[
                'value' => 'test_value',
            ],
        ];

        $this->contactSyncUrl = "https://{$this->extraParameters->host_name}.api-us1.com/api/3/contact/sync";
        $this->contactListUrl = "https://{$this->extraParameters->host_name}.api-us1.com/api/3/contactLists";
        $this->addTagsUrl = "https://{$this->extraParameters->host_name}.api-us1.com/api/3/contactTags";
    }

    /**
     * Sets property default value.
     *
     * @throws ReflectionException if apiInfo property doesn't exist
     */
    protected function tearDown(): void
    {
        parent::tearDown();

        TestHelper::setNonPublicProperty(AbstractMarketingService::class, 'apiInfo', []);
    }

    /**
     * SetUp method for creating group member(s) with associated Facebook Group and auto responder
     *
     * @param bool $isCreateGroupMembers true if more than one group members is to be created
     *         for the group, otherwise false.
     *
     * @return Collection|Model that will be used in the test case
     */
    private function groupMemberSetUp(bool $isCreateGroupMembers = false)
    {
        $facebookGroup = FacebookGroups::factory()->create(['user_id' => $this->user->id]);

        if ($isCreateGroupMembers) {
            $groupMembers = GroupMembers::factory()->count(2)->create(
                [
                    'user_id' => $this->user->id,
                    'group_id' => $facebookGroup->id,
                    'deleted_at' => null,
                ]
            );
            AutoResponder::factory()->create(
                [
                    'responder_type' => self::SERVICE_NAME,
                    'user_id' => $this->user->id,
                    'group_id' => $facebookGroup->id,
                    'responder_json' => json_encode($this->extraParameters),
                ]
            );

            return $groupMembers;
        }

        $groupMember = GroupMembers::factory()->create(
            [
                'user_id' => $this->user->id,
                'group_id' => $facebookGroup->id,
                'deleted_at' => null,
            ]
        );

        AutoResponder::factory()->create(
            [
                'responder_type' => self::SERVICE_NAME,
                'user_id' => $this->user->id,
                'group_id' => $facebookGroup->id,
                'responder_json' => json_encode($this->extraParameters),
            ]
        );

        return $groupMember;
    }

    /**
     * Asserts that provided group member has been sent to the integration
     *
     * @param GroupMembers $groupMember that will be sent to the integration
     *
     * @return string the ActiveCampaign unique contact id for this group member
     */
    protected function addOrUpdateContactSetUp(GroupMembers $groupMember): string
    {
        Http::fake([
            $this->contactSyncUrl =>
            Http::response(
                [
                    'contact' => [
                        'id' => self::CONTACT_ID,
                    ],
                ],
                Response::HTTP_OK
            ),
        ]);

        $response = Http::withHeaders(['Api-Token' => $this->extraParameters->api_key])
            ->post(
                $this->contactSyncUrl,
                [
                    'contact' => [
                        'email' => $groupMember->email,
                        'firstName' => $groupMember->f_name,
                        'lastName' => $groupMember->l_name,
                    ],
                ]
            );

        return $response->json()['contact']['id'];
    }

    /**
     * Asserts that the provided active campaign contact has been added to the mailing list
     *
     * @param string|int $activeCampaignContactId
     *          The ActiveCampaign unique id for this group member who will be subscribed to the specified list
     */
    protected function addMemberToMailingListSetUp($activeCampaignContactId)
    {
        Http::fake([
            $this->contactListUrl => Http::response([], Response::HTTP_OK),
        ]);

        $response = Http::withHeaders(['Api-Token' => $this->extraParameters->api_key])
            ->post(
                $this->contactListUrl,
                [
                    'contact' => [
                        'contact' => $activeCampaignContactId,
                        'list' => $this->extraParameters->activeList->value,
                        'status' => 1,
                    ],
                ]
            );

        $this->assertEquals(Response::HTTP_OK, $response->status());
    }

    /**
     * @test
     * that addOrUpdateContact returns contact id from ActiveCampaign integration
     *
     * @covers ::addOrUpdateContact
     *
     * @throws ReflectionException if addOrUpdateContact method is not defined.
     */
    public function addOrUpdateContact_happyPath_returnsActiveCampaignContactId()
    {
        $groupMember = $this->groupMemberSetUp();

        $contactId = $this->addOrUpdateContactSetUp($groupMember);

        $currentMock = $this->createMock(ActiveCampaignService::class);

        $response = TestHelper::callNonPublicFunction(
            $currentMock,
            'addOrUpdateContact',
            [$groupMember, $this->extraParameters]
        );

        $this->assertEquals($contactId, $response);
    }

    /**
     * @test
     * that addOrUpdateContact throws Exception with invalid request data.
     *
     * @covers ::addOrUpdateContact
     *
     * @dataProvider addOrUpdateContact_withVariousExceptions_throwsExceptionProvider
     *
     * @param string $expectedExceptionClass of the tested method call
     * @param string $expectedExceptionMessage of the tested method call
     * @param int $expectedExceptionCode of the tested method call
     *
     * @throws ReflectionException if addOrUpdateContact method is not defined.
     */
    public function addOrUpdateContact_withVariousExceptions_throwsException(
        string $expectedExceptionClass,
        string $expectedExceptionMessage,
        int $expectedExceptionCode
    ) {
        $currentMock = $this->createMock(ActiveCampaignService::class);
        $groupMember = $this->groupMemberSetUp();

        Http::fake([
            $this->contactSyncUrl =>
            Http::response([
                'error' => [
                    'message' => $expectedExceptionMessage,
                ],
            ], $expectedExceptionCode),
        ]);

        $response = Http::withHeaders(['Api-Token' => $this->extraParameters->api_key])
            ->post(
                $this->contactSyncUrl,
                [
                    'contact' => [
                        'email' => $groupMember->email,
                        'firstName' => $groupMember->f_name,
                        'lastName' => $groupMember->l_name,
                    ],
                ]
            );

        $this->expectException($expectedExceptionClass);
        $this->assertEquals($expectedExceptionMessage, $response->json()['error']['message']);

        TestHelper::callNonPublicFunction(
            $currentMock,
            'addOrUpdateContact',
            [$groupMember, $this->extraParameters]
        );
    }

    /**
     * Data provider for {@see addOrUpdateContact_withVariousExceptions_throwsException}
     *
     * @return array containing expectedExceptionClass, expectedExceptionMessage and expectedExceptionCode
     */
    public function addOrUpdateContact_withVariousExceptions_throwsExceptionProvider(): array
    {
        return [
            [
                AuthorizationException::class,
                'Invalid API key',
                Response::HTTP_FORBIDDEN,
            ],
            [
                PaymentIssuesException::class,
                'Payment issues',
                Response::HTTP_PAYMENT_REQUIRED,
            ],
            [
                RequestUnprocessableException::class,
                'Request unprocessable',
                Response::HTTP_UNPROCESSABLE_ENTITY,
            ],
            [
                RateLimitException::class,
                'Rate limit exceeded',
                Response::HTTP_TOO_MANY_REQUESTS,
            ],
            [
                ResourceNotExistException::class,
                'Resource not exist',
                Response::HTTP_NOT_FOUND,
            ],
        ];
    }

    /**
     * @test
     * that addOrUpdateContact throws InvalidStateException with invalid request data.
     *
     * @covers ::addOrUpdateContact
     *
     * @throws ReflectionException if addOrUpdateContact method is not defined.
     */
    public function addOrUpdateContact_withInvalidRequest_throwsInvalidStateException()
    {
        $currentMock = $this->createMock(ActiveCampaignService::class);
        $groupMember = $this->groupMemberSetUp();

        Http::fake([
            $this->contactSyncUrl => Http::response([], Response::HTTP_INTERNAL_SERVER_ERROR),
        ]);

        $response = Http::withHeaders(['Api-Token' => $this->extraParameters->api_key])
            ->post(
                $this->contactSyncUrl,
                [
                    'contact' => [
                        'email' => $groupMember->email,
                        'firstName' => $groupMember->f_name,
                        'lastName' => $groupMember->l_name,
                    ],
                ]
            );

        $this->expectException(InvalidStateException::class);
        $this->assertEquals(Response::HTTP_INTERNAL_SERVER_ERROR, $response->status());

        TestHelper::callNonPublicFunction(
            $currentMock,
            'addOrUpdateContact',
            [$groupMember, $this->extraParameters]
        );
    }

    /**
     * @test
     * that addMemberToMailingList given the contact id returns void response from ActiveCampaign api.
     *
     * @covers ::addMemberToMailingList
     *
     * @throws ReflectionException if addMemberToMailingList method is not defined.
     */
    public function addMemberToMailingList_givenTheContactId_returnsVoidResponse()
    {
        $this->addMemberToMailingListSetUp(self::CONTACT_ID);
        $currentMock = $this->createMock(ActiveCampaignService::class);

        TestHelper::callNonPublicFunction(
            $currentMock,
            'addMemberToMailingList',
            [self::CONTACT_ID, $this->extraParameters]
        );
    }

    /**
     * @test
     * that addMemberToMailingList throws exception with unsuccessful response from activecampaign .
     *
     * @covers ::addMemberToMailingList
     *
     * @throws ReflectionException if addMemberToMailingList method is not defined.
     */
    public function addMemberToMailingList_withUnsuccessfulApiResponse_throwsInvalidStateException()
    {
        $currentMock = $this->createMock(ActiveCampaignService::class);

        Http::fake([
            $this->contactListUrl => Http::response([], Response::HTTP_INTERNAL_SERVER_ERROR),
        ]);

        Http::withHeaders(['Api-Token' => $this->extraParameters->api_key])
            ->post(
                $this->contactListUrl,
                [
                    'contact' => [
                        'contact' => self::CONTACT_ID,
                        'list' => $this->extraParameters->activeList->value,
                        'status' => 1,
                    ],
                ]
            );
        $this->expectException(InvalidStateException::class);

        TestHelper::callNonPublicFunction(
            $currentMock,
            'addMemberToMailingList',
            [self::CONTACT_ID, $this->extraParameters]
        );
    }

    /**
     * @test
     * that addMemberToMailingList throws Exception with invalid request data.
     *
     * @covers ::addMemberToMailingList
     *
     * @dataProvider addMemberToMailingList_withVariousExceptions_throwsExceptionProvider
     *
     * @param string $expectedExceptionClass of the tested method call
     * @param string $expectedExceptionMessage of the tested method call
     * @param int $expectedExceptionCode of the tested method call
     *
     * @throws ReflectionException if addMemberToMailingList method is not defined.
     */
    public function addMemberToMailingList_withVariousExceptions_throwsException(
        string $expectedExceptionClass,
        string $expectedExceptionMessage,
        int $expectedExceptionCode
    ) {
        $currentMock = $this->createMock(ActiveCampaignService::class);

        Http::fake([
            $this->contactListUrl =>
            Http::response([
                'error' => [
                    'message' => $expectedExceptionMessage,
                ],
            ], $expectedExceptionCode),
        ]);

        $this->expectException($expectedExceptionClass);

        $response = Http::withHeaders(['Api-Token' => $this->extraParameters->api_key])
            ->post(
                $this->contactListUrl,
                [
                    'contact' => [
                        'contact' => self::CONTACT_ID,
                        'list' => $this->extraParameters->activeList->value,
                        'status' => 1,
                    ],
                ]
            );

        $this->assertEquals($expectedExceptionCode, $response->status());

        TestHelper::callNonPublicFunction(
            $currentMock,
            'addMemberToMailingList',
            [self::CONTACT_ID, $this->extraParameters]
        );
    }

    /**
     * Data provider for {@see addMemberToMailingList_withVariousExceptions_throwsException}
     *
     * @return array containing expectedExceptionClass, expectedExceptionMessage and expectedExceptionCode
     */
    public function addMemberToMailingList_withVariousExceptions_throwsExceptionProvider(): array
    {
        return [
            [
                AuthorizationException::class,
                'Invalid API key',
                Response::HTTP_FORBIDDEN,
            ],
            [
                PaymentIssuesException::class,
                'Payment issues',
                Response::HTTP_PAYMENT_REQUIRED,
            ],
            [
                RequestUnprocessableException::class,
                'Request unprocessable',
                Response::HTTP_UNPROCESSABLE_ENTITY,
            ],
            [
                RateLimitException::class,
                'Rate limit exceeded',
                Response::HTTP_TOO_MANY_REQUESTS,
            ],
            [
                ResourceNotExistException::class,
                'Resource not exist',
                Response::HTTP_NOT_FOUND,
            ],
        ];
    }

    /**
     * SetUp method for
     * {@see addTags_happyPath_returnsSuccessResponse}
     * {@see addTags_whenApiReturnsErrorHttpCode_throwsInvalidException}
     *
     * @return MockObject for {@see ActiveCampaignService} class
     */
    private function addTagsSetUp(): MockObject
    {
        $this->extraParameters = (object)[
            'api_key' => 'test_api_key',
            'host_name' => 'test_host_name',
            'activeList' => (object)[
                'value' => 'test_value',
            ],
            'activeTags' => (object)[
                'label' => 'Pro Users',
                'value' => 'tag-pro',
            ],
        ];

        return $this->createMock(ActiveCampaignService::class);
    }

    /**
     * @test
     * that addTags API returns {@see Response::HTTP_OK} response
     *
     * @covers ::addTags
     *
     * @throws ReflectionException if addTags method is not defined.
     */
    public function addTags_happyPath_returnsSuccessResponse()
    {
        $currentMock = $this->addTagsSetUp();

        Http::fake([
            $this->addTagsUrl => Http::response([], Response::HTTP_OK),
        ]);

        $response = Http::withHeaders(['Api-Token' => $this->extraParameters->api_key])
            ->post(
                $this->addTagsUrl,
                [
                    'contactTag' => [
                        'contact' => self::CONTACT_ID,
                        'tag' => $this->extraParameters->activeTags->value,
                    ],
                ]
            );

        $this->assertEquals(Response::HTTP_OK, $response->status());

        TestHelper::callNonPublicFunction(
            $currentMock,
            'addTags',
            [self::CONTACT_ID, $this->extraParameters]
        );
    }

    /**
     * @test
     * that addTags throws {@see InvalidStateException} when API returns error HTTP status code
     *
     * @covers ::addTags
     *
     * @throws ReflectionException if addTags method is not defined.
     */
    public function addTags_whenApiReturnsErrorHttpCode_throwsInvalidException()
    {
        $currentMock = $this->addTagsSetUp();

        Http::fake([
            $this->addTagsUrl => Http::response([], Response::HTTP_INTERNAL_SERVER_ERROR),
        ]);

        $this->expectException(InvalidStateException::class);

        $response = Http::withHeaders(['Api-Token' => $this->extraParameters->api_key])
            ->post(
                $this->addTagsUrl,
                [
                    'contactTag' => [
                        'contact' => self::CONTACT_ID,
                        'tag' => $this->extraParameters->activeTags->value,
                    ],
                ]
            );

        $this->assertEquals(Response::HTTP_INTERNAL_SERVER_ERROR, $response->status());

        TestHelper::callNonPublicFunction(
            $currentMock,
            'addTags',
            [self::CONTACT_ID, $this->extraParameters]
        );
    }

    /**
     * @test
     * that subscribeAll sends group members to the integration
     *
     * @covers ::subscribeAll
     *
     * @dataProvider subscribeAll_withDifferentRequestFromExtensionStatusesProvider
     *
     * @param bool $requestIsFromExtension true if the request is from extension, false otherwise.
     */
    public function subscribeAll_withDifferentRequestFromExtensionStatuses_sendsGroupMembersToTheIntegration(
        bool $requestIsFromExtension
    ) {
        $groupMembers = $this->groupMemberSetUp(true);

        $contactIds = [];

        foreach ($groupMembers as $groupMember) {
            $contactIds[] = $this->addOrUpdateContactSetUp($groupMember);
        }
        $this->addMemberToMailingListSetUp($contactIds[0]);

        app(ActiveCampaignService::class)->subscribeAll($groupMembers, $requestIsFromExtension);

        $groupMembersCount = $groupMembers->count();
        for ($i = 0; $i < $groupMembersCount; $i++) {
            $this->assertDatabaseHas(
                'group_members',
                [
                    'group_id' => $groupMembers[$i]->group_id,
                    'respond_status' => GroupMembers::RESPONSE_STATUSES['ADDED'],
                ]
            );

            $requestIsFromExtensionAssertation = $requestIsFromExtension ? 'assertNotNull' : 'assertNull';
            $this->$requestIsFromExtensionAssertation(
                GroupMembers::find($groupMembers[$i]->id)->respond_date_time
            );

            $this->assertEquals(self::CONTACT_ID, $contactIds[$i]);
        }
    }

    /**
     * Data provider for {@see subscribeAll_withDifferentRequestFromExtensionStatuses_sendsGroupMembersToTheIntegration}
     *
     * @return array containing requestIsFromExtension and expectedDateTimeValue
     */
    public function subscribeAll_withDifferentRequestFromExtensionStatusesProvider(): array
    {
        return [
            'Request does not comes from Chrome Extension' => [
                'requestIsFromExtension' => true,
            ],
            'Request comes from the Chrome Extension' => [
                'requestIsFromExtension' => false,
            ],
        ];
    }

    /**
     * @test
     * that subscribeAll with various exceptions updates the group member respond status.
     *
     * @covers ::subscribeAll
     *
     * @dataProvider subscribeAll_withVariousExceptions_updatesGroupMemberRespondStatusProvider
     *
     * @param string $expectedExceptionClass of the tested method call
     * @param int $expectedStatusCode of the tested method call
     * @param string $expectedRespondStatus for the group members sent via tested method
     */
    public function subscribeAll_withVariousException_updatesGroupMemberRespondStatus(
        string $expectedExceptionClass,
        int $expectedStatusCode,
        string $expectedRespondStatus
    ) {
        $groupMembers = $this->groupMemberSetUp(true);

        Http::fake([
            $this->contactSyncUrl =>
            Http::response(
                [
                    'contact' => [
                        'id' => self::CONTACT_ID,
                    ],
                ],
                $expectedStatusCode
            ),
        ]);

        foreach ($groupMembers as $groupMember) {
            Http::withHeaders(['Api-Token' => $this->extraParameters->api_key])
                ->post(
                    $this->contactSyncUrl,
                    [
                        'contact' => [
                            'email' => $groupMember->email,
                            'firstName' => $groupMember->f_name,
                            'lastName' => $groupMember->l_name,
                        ],
                    ]
                );
        }

        app(ActiveCampaignService::class)->subscribeAll($groupMembers, false);

        $groupMembersCount = $groupMembers->count();
        for ($i = 0; $i < $groupMembersCount; $i++) {
            $this->assertDatabaseHas(
                'group_members',
                [
                    'group_id' => $groupMembers[$i]->group_id,
                    'respond_status' => $expectedRespondStatus,
                ]
            );
        }
    }

    /**
     * Data provider for {@see subscribeAll_withVariousException_updatesGroupMemberRespondStatus}
     *
     * @return array containing expectedExceptionClass and expectedRespondStatus
     */
    public function subscribeAll_withVariousExceptions_updatesGroupMemberRespondStatusProvider(): array
    {
        return [
            [
                AuthorizationException::class,
                ActiveCampaignService::REQUEST_UNAUTHORIZED,
                GroupMembers::RESPONSE_STATUSES['ACTIVE_CAMPAIGN_AUTHORIZATION_ISSUE'],
            ],
            [
                PaymentIssuesException::class,
                ActiveCampaignService::REQUEST_UNPROCESSABLE_DUE_TO_PAYMENT_ISSUES,
                GroupMembers::RESPONSE_STATUSES['ACTIVE_CAMPAIGN_PAYMENT_ISSUE'],
            ],
            [
                RequestUnprocessableException::class,
                ActiveCampaignService::REQUEST_UNPROCESSABLE,
                GroupMembers::RESPONSE_STATUSES['ACTIVE_CAMPAIGN_REQUEST_UNPROCESSABLE'],
            ],
            [
                ResourceNotExistException::class,
                ActiveCampaignService::REQUEST_RESOURCE_NOT_EXIST,
                GroupMembers::RESPONSE_STATUSES['ACTIVE_CAMPAIGN_RESOURCE_NOT_EXIST'],
            ],
            [
                ResourceNotExistException::class,
                ActiveCampaignService::NO_RESULT_FOUND,
                GroupMembers::RESPONSE_STATUSES['ACTIVE_CAMPAIGN_RESOURCE_NOT_EXIST'],
            ],
        ];
    }

    /**
     * @test
     * that subscribeAll notifies BugSnag when {@see RateLimitException} have been thrown
     *
     * @covers ::subscribeAll
     */
    public function subscribeAll_whenSubscribeThrowsRateLimitException_notifiesBugSnag()
    {
        $groupMembers = $this->groupMemberSetUp(true);

        Http::fake([
            $this->contactSyncUrl =>
            Http::response(
                [
                    'contact' => [
                        'id' => self::CONTACT_ID,
                    ],
                ],
                ActiveCampaignService::RATE_LIMIT_EXCEEDED
            ),
        ]);

        Bugsnag::shouldReceive('notifyException')->twice();
        Bugsnag::shouldReceive('leaveBreadcrumb');

        foreach ($groupMembers as $groupMember) {
            Http::withHeaders(['Api-Token' => $this->extraParameters->api_key])
                ->post(
                    $this->contactSyncUrl,
                    [
                        'contact' => [
                            'email' => $groupMember->email,
                            'firstName' => $groupMember->f_name,
                            'lastName' => $groupMember->l_name,
                        ],
                    ]
                );
        }

        app(ActiveCampaignService::class)->subscribeAll($groupMembers, false);
    }

    /**
     * @test
     * that subscribeAll
     * 1. notifies BugSnag
     * 2. update respond_status field to the 'Not Added'
     * when subscribe throws {@see InvalidStateException}
     *
     * @covers ::subscribeAll
     */
    public function subscribeAll_whenSubscribeThrowsInvalidStateException_setsNotAddedStatusForGroupMembers()
    {
        $groupMembers = $this->groupMemberSetUp(true);

        Http::fake([
            $this->contactSyncUrl =>
            Http::response(
                [
                    'contact' => [
                        'id' => self::CONTACT_ID,
                    ],
                ],
                Response::HTTP_BAD_REQUEST
            ),
        ]);

        Bugsnag::shouldReceive('notifyException')->twice();
        Bugsnag::shouldReceive('leaveBreadcrumb');

        foreach ($groupMembers as $groupMember) {
            Http::withHeaders(['Api-Token' => $this->extraParameters->api_key])
                ->post(
                    $this->contactSyncUrl,
                    [
                        'contact' => [
                            'email' => $groupMember->email,
                            'firstName' => $groupMember->f_name,
                            'lastName' => $groupMember->l_name,
                        ],
                    ]
                );
        }

        app(ActiveCampaignService::class)->subscribeAll($groupMembers, false);

        $groupMembersCount = $groupMembers->count();
        for ($i = 0; $i < $groupMembersCount; $i++) {
            $this->assertDatabaseHas(
                'group_members',
                [
                    'group_id' => $groupMembers[$i]->group_id,
                    'respond_status' => GroupMembers::RESPONSE_STATUSES['NOT_ADDED'],
                ]
            );
        }
    }
}
