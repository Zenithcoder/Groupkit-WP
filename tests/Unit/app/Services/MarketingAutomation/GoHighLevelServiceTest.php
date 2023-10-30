<?php

namespace Tests\Unit\app\Services\MarketingAutomation;

use App\AutoResponder;
use App\Exceptions\InvalidStateException;
use App\FacebookGroups;
use App\GroupMembers;
use App\Services\MarketingAutomation\AbstractMarketingService;
use App\Services\MarketingAutomation\GoHighLevelService;
use App\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\RequestException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Client\Response as ClientResponse;
use ReflectionException;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use Tests\TestCase;
use Tests\TestHelper;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Class IntegrationServiceTest adds test coverage for {@see GoHighLevelService}
 *
 * @package Tests\Unit\app\Services\GoHighLevelService
 * @coversDefaultClass \App\Services\MarketingAutomation\GoHighLevelService
 */
class GoHighLevelServiceTest extends TestCase
{
    use DatabaseTransactions;
    use DatabaseMigrations;

    /**
     * Name of the marketing service that will be covered with test cases
     *
     * @var string
     */
    private const SERVICE_NAME = 'GoHighLevel';

    /**
     * Service url for Go high level service api calling
     *
     * @var string
     */
    private const SERVICE_URL = 'https://api.gohighlevel.com/campaign/start';

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
     * Setup test dependencies
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->user = $this->actingAsUser();

        $this->extraParameters = (object)[
            'activeList' => (object)[
                'label' => 'FB Group',
                'value' => 'LypjIyZHNyYH5eUgL046',
            ],
            'api_key' => '5fed5b2c-c3f6-4148-bc2a-c1cb39a52dbd',
        ];
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
     * SetUp method for creating group member with associated Facebook Group and auto responder
     *
     * @return GroupMembers that will be used in the test case
     */
    private function groupMemberSetUp(): GroupMembers
    {
        $facebookGroup = FacebookGroups::factory()->create(['user_id' => $this->user->id]);

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
     * @param MockObject $responseMock as the expected response of the service
     */
    protected function addOrUpdateContactSetUp(GroupMembers $groupMember, MockObject $responseMock): void
    {
        Http::shouldReceive('withHeaders')
            ->withArgs([
                [
                    'Content-Type' => 'application/json',
                    'Authorization' => "Bearer {$this->extraParameters->api_key}",
                ]
            ])
            ->andReturnSelf()
            ->shouldReceive('post')
            ->withArgs([
                self::SERVICE_URL,
                [
                    'campaign_id' => $this->extraParameters->activeList->value,
                    'first_name' => $groupMember->f_name,
                    'last_name' => $groupMember->l_name,
                    'name' => '',
                    'email' => $groupMember->email,
                ]
            ])
            ->andReturn($responseMock);
    }

    /**
     * @test
     * that subscribe sends group member to the integration
     *
     * @covers ::subscribe
     */
    public function subscribe_happyPath_sendsGroupMemberToTheIntegration()
    {
        $groupMember = $this->groupMemberSetUp();

        $responseMock = $this->createMock(ClientResponse::class);
        $responseMock->method('status')->willReturn(HttpResponse::HTTP_OK);
        $this->addOrUpdateContactSetUp($groupMember, $responseMock);

        app(GoHighLevelService::class)->subscribe($groupMember);
    }

    /**
     * @test
     * that subscribe throws an exception while add or update contact
     * {@see \App\Services\MarketingAutomation\GoHighLevelService::addOrUpdateContact} method throws an exception
     *
     * @covers ::subscribe
     */
    public function subscribe_whenAddOrUpdateContactThrowsAnException_throwsAnException()
    {
        $groupMember = $this->groupMemberSetUp();

        $requestException = RequestException::class;

        $this->expectException($requestException);

        Http::shouldReceive('withHeaders')
            ->withArgs([
                [
                    'Content-Type' => 'application/json',
                    'Authorization' => "Bearer {$this->extraParameters->api_key}",
                ]
            ])
            ->andReturnSelf()
            ->shouldReceive('post')
            ->withArgs([
                self::SERVICE_URL,
                [
                    'campaign_id' => $this->extraParameters->activeList->value,
                    'first_name' => $groupMember->f_name,
                    'last_name' => $groupMember->l_name,
                    'name' => '',
                    'email' => $groupMember->email,
                ]
            ])
            ->andThrows($this->mock($requestException));

        app(GoHighLevelService::class)->subscribe($groupMember);
    }

    /**
     * @test
     * that addOrUpdateContact returns void response when request is succeed with HTTP_OK response
     *
     * @covers ::addOrUpdateContact
     *
     * @throws ReflectionException if addOrUpdateContact method is not defined
     */
    public function addOrUpdateContact_happyPath_returnsVoidResponse()
    {
        $groupMember = $this->groupMemberSetUp();

        $currentMock = $this->createMock(GoHighLevelService::class);

        $responseMock = $this->createMock(ClientResponse::class);
        $responseMock->method('status')->willReturn(HttpResponse::HTTP_OK);
        $this->addOrUpdateContactSetUp($groupMember, $responseMock);

        TestHelper::callNonPublicFunction(
            $currentMock,
            'addOrUpdateContact',
            [$groupMember, $this->extraParameters]
        );
    }

    /**
     * @test
     * that addOrUpdateContact return exception and HTTP_BAD_REQUEST response when request is not succeed
     *
     * @covers ::addOrUpdateContact
     *
     * @throws ReflectionException if addOrUpdateContact method is not defined
     */
    public function addOrUpdateContact_withInvalidRequest_returnsExceptionMessage()
    {
        $facebookGroup = FacebookGroups::factory()->create(['user_id' => $this->user->id]);

        $groupMember = GroupMembers::factory()->create(
            [
                'user_id' => $this->user->id,
                'group_id' => $facebookGroup->id,
                'deleted_at' => null,
            ]
        );

        $currentMock = $this->createMock(GoHighLevelService::class);

        $responseMock = $this->createMock(ClientResponse::class);
        $responseMock->method('status')->willReturn(HttpResponse::HTTP_BAD_REQUEST);
        $this->addOrUpdateContactSetUp($groupMember, $responseMock);

        $this->expectException(InvalidStateException::class);
        $this->expectExceptionMessage('Not Added');

        TestHelper::callNonPublicFunction(
            $currentMock,
            'addOrUpdateContact',
            [$groupMember, $this->extraParameters]
        );
    }
}
