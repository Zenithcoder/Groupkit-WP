<?php

namespace Tests\Unit\app\Services\MarketingAutomation;

use App\AutoResponder;
use App\Exceptions\InvalidStateException;
use App\FacebookGroups;
use App\GroupMembers;
use App\Services\MarketingAutomation\AbstractMarketingService;
use App\Services\MarketingAutomation\InfusionSoftService;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Symfony\Component\HttpFoundation\Response;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\HttpFoundation\Response as responseCode;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\Response as ClientResponse;
use Illuminate\Http\Client\RequestException;
use Tests\TestCase;
use Tests\TestHelper;
use GuzzleHttp\Client;
use App\User;
use stdClass;
use ReflectionException;

/**
 * Class InfusionSoftServiceTest adds test coverage for {@see InfusionSoftService}
 *
 * @package Tests\Unit\app\Services\MarketingAutomation
 * @coversDefaultClass \App\Services\MarketingAutomation\InfusionSoftService
 */
class InfusionSoftServiceTest extends TestCase
{
    use DatabaseTransactions;
    use DatabaseMigrations;

    /**
     * The URL of InfusionSoft service which is used to call InfusionSoft APIs.
     *
     * @var string
     */
    private string $serviceUrl;

    /**
     * @var User contains newly created user object
     */
    private User $user;

    /**
     * @var stdClass $requestData contains client id, client secret, authorize code, refresh token, access token.
     */
    protected stdClass $requestData;

    /**
     * Setup test dependencies
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->serviceUrl = 'https://api.infusionsoft.com/';

        $this->requestData = new stdClass();
        $this->requestData->clientId = 'GQiDUThAmipo29AjA0rQxdItoSuNxKR3';
        $this->requestData->clientSecret = 'DG8V3ybtlYDtngjf';
        $this->requestData->authorizeCode = '8b8G7UGa';
        $this->requestData->refreshToken = 'AR0PeQHFfd5lxl07jOXkOXJAGpKnA3JL';
        $this->requestData->accessToken = 'ZQmEaPXTD88p6USoVnU9mupbXxA6';
        $this->requestData->activeTags = [(object)['value' => 13], (object)['value' => 32]];

        $this->user = $this->actingAsApiUser();
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
     * @param bool $activeTags if there are active tags in the request.
     *
     * @return GroupMembers that will be used in the test case
     */
    private function groupMemberSetUp(bool $activeTags = true)
    {
        $facebookGroup = FacebookGroups::factory()->create(['user_id' => $this->user->id]);

        $groupMember = GroupMembers::factory()->create([
            'f_name' => 'may',
            'l_name' => 'day',
            'a1' => 'Question 1 desc',
            'a2' => 'Question 2 desc',
            'a3' => 'Question 3 desc',
            'user_id' => $this->user->id,
            'group_id' => $facebookGroup->id,
            'respond_status' => null,
            'deleted_at' => null,
            'email' => $this->user->email,
        ]);

        $responderJson = [
            'clientId' => $this->requestData->clientId,
            'clientSecret' => $this->requestData->clientSecret,
            'authorizeCode' => $this->requestData->authorizeCode,
            'accessToken' => $this->requestData->accessToken,
            'refreshToken' => $this->requestData->refreshToken,
        ];

        AutoResponder::factory()->create([
            'responder_type' => 'InfusionSoft',
            'user_id' => $this->user->id,
            'group_id' => $facebookGroup->id,
            'responder_json' => json_encode(
                $activeTags
                    ? array_merge($responderJson, ['activeTags' => $this->requestData->activeTags])
                    : $responderJson
            ),
        ]);

        return $groupMember;
    }

    /**
     * Asserts that provided group member has been sent to the integration
     *
     * @param GroupMembers $groupMember that will be sent to the integration
     * @param object $extraParameters contains client id, client secret, authorize code, refresh token, access token.
     *
     * @return int the InfusionSoft unique contact id for this group member
     */
    protected function addOrUpdateContactSetUp(GroupMembers $groupMember, object $extraParameters): int
    {
        $contactId = 1039;
        $responseBody = json_encode((object) ['id' => $contactId]);
        $clientMock = $this->mock(Client::class);
        $responseMock = $this->createMock(ResponseInterface::class);
        $responseMock->expects(static::once())->method('getStatusCode')->willReturn(responseCode::HTTP_OK);
        $responseMock->expects(static::once())->method('getBody')->willReturn($responseBody);

        $clientMock->shouldReceive('put')
            ->withArgs(
                [
                    'https://api.infusionsoft.com/crm/rest/v1/contacts?access_token=' . $extraParameters->accessToken,
                    [
                        'headers' => [
                            'Content-Type' => 'application/json',
                        ],
                        'json' => [
                            'email_addresses' => [
                                [
                                    'email' => $groupMember->email,
                                    'field' => 'EMAIL1',
                                ],
                            ],
                            'family_name' => $groupMember->l_name,
                            'given_name' => $groupMember->f_name,
                            'duplicate_option' => 'Email', //Performs duplicate checking by 'Email'
                        ],
                        'http_errors' => false,
                    ],
                ]
            )
            ->andReturn($responseMock);

        $this->app->bind(
            Client::class,
            function () use ($clientMock) {
                return $clientMock;
            }
        );

        return $contactId;
    }

    /**
     * Adds tags to the specified InfusionSoft contact
     *
     * @param int $infusionSoftContactId
     *          The InfusionSoft unique id for group member
     * @param object $extraParameters
     *          which contain the app id app key for authentication,
     *          i.e. {client id, client secret, access token, refresh token, activeTags}
     *
     * @return void
     */
    protected function addTagsToContactSetUp(int $infusionSoftContactId, object $extraParameters): void
    {
        $responseMock = $this->createMock(ClientResponse::class);
        $responseMock->method('status')->willReturn(Response::HTTP_OK);

        $url = sprintf(
            '%scrm/rest/v1/contacts/%s/tags?access_token=%s',
            $this->serviceUrl,
            $infusionSoftContactId,
            $extraParameters->accessToken,
        );

        $tagIds = array_map(function ($activeTag) {
            return $activeTag->value;
        }, $extraParameters->activeTags);

        Http::shouldReceive('withHeaders')
            ->withArgs([
                [
                    'Content-Type' => 'application/json',
                ]
            ])
            ->andReturnSelf()
            ->shouldReceive('post')
            ->withArgs([
                $url,
                [
                    'tagIds' => $tagIds,
                ]
            ])
            ->andReturn($responseMock);
    }

    /**
     * Asserts that exception is thrown from the {@see \GuzzleHttp\ClientTrait::post} method
     *
     * @param GroupMembers $groupMember that will be sent to the integration
     * @param object $extraParameters contains client id, client secret, authorize code, refresh token, access token
     */
    private function addOrUpdateContactThrowsAnExceptionSetUp(
        GroupMembers $groupMember,
        object $extraParameters
    ) {
        $expectedResponse = new \stdClass();
        $expectedResponse->fault = new \stdClass();
        $expectedResponse->fault->faultstring = 'Invalid Access Token';
        $expectedResponse->fault->detail = new \stdClass();
        $expectedResponse->fault->detail->errorcode = 'keymanagement.service.invalid_access_token';

        $clientMock = $this->mock(Client::class);
        $responseMock = $this->createMock(ResponseInterface::class);
        $responseMock->expects(static::once())->method('getStatusCode')->willReturn(responseCode::HTTP_BAD_REQUEST);
        $responseMock->expects(static::once())->method('getBody')->willReturn(json_encode($expectedResponse));

        $clientMock->shouldReceive('put')
            ->withArgs(
                [
                    'https://api.infusionsoft.com/crm/rest/v1/contacts?access_token=' . $extraParameters->accessToken,
                    [
                        'headers' => [
                            'Content-Type' => 'application/json',
                        ],
                        'json' => [
                            'email_addresses' => [
                                [
                                    'email' => $groupMember->email,
                                    'field' => 'EMAIL1',
                                ],
                            ],
                            'family_name' => $groupMember->l_name,
                            'given_name' => $groupMember->f_name,
                            'duplicate_option' => 'Email', //Performs duplicate checking by 'Email'
                        ],
                        'http_errors' => false,
                    ],
                ]
            )
            ->andThrows($responseMock);

        $this->app->bind(
            Client::class,
            function () use ($clientMock) {
                return $clientMock;
            }
        );
    }

    /**
     * Setup method for requestAccessToken
     *
     * @return array which contains expected Response.
     */
    private function requestAccessTokenSetUp(): array
    {
        $expectedResponse = [
            'message' => 'Verification completed successfully.',
            'body' => (object)[
                'scope' => 'full|xmk779.infusionsoft.com',
                'access_token' => $this->requestData->accessToken,
                'token_type' => 'bearer',
                'expires_in' => 86399,
                'refresh_token' => $this->requestData->refreshToken
            ],
            'code' => Response::HTTP_OK,
        ];

        $responseMock = $this->mock(ResponseInterface::class);
        $responseMock->shouldReceive('getStatusCode')->andReturns(responseCode::HTTP_OK);
        $responseMock->shouldReceive('getBody')->andReturns(json_encode($expectedResponse['body']));

        $clientMock = $this->mock(Client::class);

        $clientMock->shouldReceive('post')
            ->withSomeOfArgs('https://api.infusionsoft.com/token')
            ->andReturn($responseMock);

        $this->app->bind(Client::class, function () use ($clientMock) {
            return $clientMock;
        });

        return $expectedResponse;
    }

    /**
     * Setup method for request access token generation
     *
     * @return array returns error message with error code.
     */
    private function requestAccessTokenExceptionSetUp(): array
    {
        $expectedResponse = [
            'message' => 'Invalid Request',
            'code' => Response::HTTP_BAD_REQUEST,
        ];

        $clientException = $this->createMock(InvalidStateException::class);

        $clientMock = $this->mock(Client::class);

        $clientMock->shouldReceive('post')
            ->withSomeOfArgs('https://api.infusionsoft.com/token')
            ->andThrow($clientException);

        $this->app->bind(Client::class, function () use ($clientMock) {
            return $clientMock;
        });

        return $expectedResponse;
    }

    /**
     * Setup method for refresh access token
     *
     * @param string $clientId
     * @param string $clientSecret
     * @param string $refreshToken
     */
    protected function refreshAccessTokenSetUp(string $clientId, string $clientSecret, string $refreshToken): void
    {
        $expectedResponse = [
            'message' => 'Token has been refreshed successfully.',
            'code' => Response::HTTP_OK,
            'body' => (object)[
                'scope' => 'full|xmk779.infusionsoft.com',
                'access_token' => 'gqvEQpWNIUATygrZOwodqFNKAka9',
                'token_type' => 'bearer',
                'expires_in' => 86399,
                'refresh_token' => 'dY9nIgTzouRMD1FEZGIyYvd1lK0zxCb9'
            ],
        ];

        $responseMock = $this->mock(ResponseInterface::class);
        $responseMock->shouldReceive('getStatusCode')->andReturns(responseCode::HTTP_OK);
        $responseMock->shouldReceive('getBody')->andReturns(json_encode($expectedResponse['body']));

        $clientMock = $this->mock(Client::class);
        $clientMock->shouldReceive('post')
            ->withArgs(
                [
                    'https://api.infusionsoft.com/token',
                    [
                        'headers' => [
                            'Authorization' => 'Basic ' . base64_encode($clientId . ':' . $clientSecret),
                            'Content-Type' => 'application/x-www-form-urlencoded',
                        ],
                        'body' => http_build_query([
                                'client_id' => $clientId,
                                'client_secret' => $clientSecret,
                                'refresh_token' => $refreshToken,
                                'grant_type' => 'refresh_token',
                                'redirect_uri' => url('/infusionSoftAuth/callback'),
                            ]),
                    ],
                ]
            )
            ->andReturn($responseMock);

        $this->app->bind(
            Client::class,
            function () use ($clientMock) {
                return $clientMock;
            }
        );
    }

    /**
     * @test
     * that index returns single user's information from InfusionSoft integration
     *
     * @covers ::subscribe
     */
    public function subscribe_withListData_returnsVoid()
    {
        $groupMember = $this->groupMemberSetUp();

        $infusionSoftContactId = $this->addOrUpdateContactSetUp($groupMember, $this->requestData);
        $this->addTagsToContactSetUp($infusionSoftContactId, $this->requestData);

        app(InfusionSoftService::class)->subscribe($groupMember);
    }

    /**
     * @test
     * that subscribe() method of InfusionSoftService will not throw exception: Undefined Property $activeTags,
     * if there is no such property in the request. This test will fail if we ommit the check
     * isset($extraParameters->activeTags) in the if statement in the subscribe() method.
     * Since there is no tags in the request, they won't be added to the member.
     *
     * @covers ::subscribe
     */
    public function subscribe_withoutActiveTags_doesntAddTagsToContact(): void
    {
        $groupMember = $this->groupMemberSetUp(false);

        unset($this->requestData->activeTags);
        $this->addOrUpdateContactSetUp($groupMember, $this->requestData);

        Http::shouldReceive('post')->never();

        app(InfusionSoftService::class)->subscribe($groupMember);
    }

    /**
     * @test
     * that requestAccessToken returns new access token, refresh token from InfusionSoft integration.
     *
     * @covers ::requestAccessToken
     */
    public function requestAccessToken_withRequestData_returnsSuccessResponse()
    {
        $expectedResponse = $this->requestAccessTokenSetUp();

        $result = app(InfusionSoftService::class)->requestAccessToken(
            $this->requestData->clientId,
            $this->requestData->clientSecret,
            $this->requestData->authorizeCode
        );

        $this->assertEquals($expectedResponse, $result);
    }

    /**
     * @test
     * that requestAccessToken returns exception while getting new access token, refresh token
     * from InfusionSoft integration
     *
     * @covers ::requestAccessToken
     */
    public function requestAccessToken_withRequestData_returnsException()
    {
        $expectedResponse = $this->requestAccessTokenExceptionSetUp();

        $result = app(InfusionSoftService::class)->requestAccessToken(
            $this->requestData->clientId,
            $this->requestData->clientSecret,
            $this->requestData->authorizeCode
        );

        $this->assertEquals($expectedResponse, $result);
    }

    /**
     * @test
     * that refreshAccessToken returns single user's information from InfusionSoft integration
     *
     * @covers ::refreshAccessToken
     */
    public function refreshAccessToken_withRequestData_returnsException()
    {
        $expectedResponse = [
            'message' => 'Invalid Request',
            'code' => Response::HTTP_BAD_REQUEST,
        ];

        $clientException = $this->createMock(InvalidStateException::class);

        $clientMock = $this->mock(Client::class);

        $clientMock->shouldReceive('post')
            ->withSomeOfArgs('https://api.infusionsoft.com/token')
            ->andThrow($clientException);

        $this->app->bind(Client::class, function () use ($clientMock) {
            return $clientMock;
        });

        $response = app(InfusionSoftService::class)->refreshAccessToken(
            $this->requestData->clientId,
            $this->requestData->clientSecret,
            $this->requestData->refreshToken
        );

        $this->assertEquals($expectedResponse, $response);
    }

    /**
     * @test
     * that refreshAccessToken returns single user's information from InfusionSoft integration
     *
     * @covers ::refreshAccessToken
     */
    public function refreshAccessToken_withRequestData_returnsSuccessResponse()
    {
        $expectedResponse = [
            'message' => 'Token has been refreshed successfully.',
            'code' => Response::HTTP_OK,
            'body' => (object)[
                'scope' => 'full|xmk779.infusionsoft.com',
                'access_token' => 'gqvEQpWNIUATygrZOwodqFNKAks6',
                'token_type' => 'bearer',
                'expires_in' => 86399,
                'refresh_token' => 'dY9nIgTzouRMD1FEZGIyYvd1lK0zxCqO'
            ],
        ];

        $responseMock = $this->mock(ResponseInterface::class);
        $responseMock->shouldReceive('getStatusCode')->andReturns(responseCode::HTTP_OK);
        $responseMock->shouldReceive('getBody')->andReturns(json_encode($expectedResponse['body']));

        $clientMock = $this->mock(Client::class);
        $clientMock->shouldReceive('post')
            ->withSomeOfArgs('https://api.infusionsoft.com/token')
            ->andReturn($responseMock);

        $this->app->bind(Client::class, function () use ($clientMock) {
            return $clientMock;
        });

        $response = app(InfusionSoftService::class)->refreshAccessToken(
            $this->requestData->clientId,
            $this->requestData->clientSecret,
            $this->requestData->refreshToken
        );

        $this->assertEquals($expectedResponse, $response);
    }

    /**
     * @test
     * that addOrUpdateContact returns contact id from InfusionSoft integration
     *
     * @covers ::addOrUpdateContact
     *
     * @throws ReflectionException if addOrUpdateContact method is not defined.
     */
    public function addOrUpdateContact_withRequestData_returnsInfusionSoftContactId()
    {
        $groupMember = $this->groupMemberSetUp();

        $this->addOrUpdateContactSetUp($groupMember, $this->requestData);

        $currentMock = $this->createMock(InfusionSoftService::class);

        $response = TestHelper::callNonPublicFunction(
            $currentMock,
            'addOrUpdateContact',
            [$groupMember, $this->requestData]
        );

        $this->assertIsInt($response);
    }

    /**
     * @test
     * that addOrUpdateContact returns single user's information from InfusionSoft integration
     *
     * @covers ::addOrUpdateContact
     *
     * @throws ReflectionException if addOrUpdateContact method is not defined.
     */
    public function addOrUpdateContact_withRequestData_returnsErrorResponse()
    {
        $groupMember = $this->groupMemberSetUp();

        $this->addOrUpdateContactThrowsAnExceptionSetUp($groupMember, $this->requestData);

        $this->expectException(InvalidStateException::class);
        $this->expectExceptionMessage('Not Added');

        $currentMock = $this->createMock(InfusionSoftService::class);

        TestHelper::callNonPublicFunction(
            $currentMock,
            'addOrUpdateContact',
            [$groupMember, $this->requestData]
        );
    }

    /**
     * @test
     * that getRefreshedApiInfo returns single user's information from InfusionSoft integration
     *
     * @covers ::getRefreshedApiInfo
     *
     * @throws ReflectionException if getRefreshedApiInfo method is not defined.
     */
    public function getRefreshedApiInfo_withRequestData_returnsCatchException()
    {
        $currentMock = $this->createMock(InfusionSoftService::class);

        $groupMember = $this->groupMemberSetUp();

        $expectedResponse = $this->requestAccessTokenExceptionSetUp();

        $response = TestHelper::callNonPublicFunction(
            $currentMock,
            'getRefreshedApiInfo',
            [$groupMember, $this->requestData]
        );

        $this->assertEquals($expectedResponse['message'], $response->message);
    }

    /**
     * @test
     * that getRefreshedApiInfo returns single user's information from InfusionSoft integration
     *
     * @covers ::getRefreshedApiInfo
     *
     * @throws ReflectionException if getRefreshedApiInfo method is not defined.
     */
    public function getRefreshedApiInfo_withRequestData_returnsSuccessResponse()
    {
        $expectedResponse = new stdClass();
        $expectedResponse->clientId = 'GQiDUThAmipo29AjA0rQxdItoSuNxKR3';
        $expectedResponse->clientSecret = 'DG8V3ybtlYDtngjf';
        $expectedResponse->authorizeCode = '8b8G7UGa';
        $expectedResponse->refreshToken = 'dY9nIgTzouRMD1FEZGIyYvd1lK0zxCb9';
        $expectedResponse->accessToken = 'gqvEQpWNIUATygrZOwodqFNKAka9';

        $currentMock = $this->createMock(InfusionSoftService::class);

        $groupMember = $this->groupMemberSetUp();

        $requestData = $this->requestData;
        unset($requestData->activeTags);

        $this->refreshAccessTokenSetUp(
            $requestData->clientId,
            $requestData->clientSecret,
            $requestData->refreshToken
        );

        $response = TestHelper::callNonPublicFunction(
            $currentMock,
            'getRefreshedApiInfo',
            [$groupMember, $requestData]
        );

        $this->assertEquals($expectedResponse, $response);
    }

    /**
     * @test
     * that getTags returns array of available client tags and success response from InfusionSoft integration
     *
     * @covers ::getTags
     */
    public function getTags_withRequestData_returnsDataWithSuccessResponse()
    {
        $json = [
            'tags' => [
                    'id' => 101,
                    'name' => 'Interest - Email Series',
                    'description' => '',
                    'category' => [
                        'id' => 4,
                        'name' => 'Prospect Tags',
                        'description' => null,
                    ],
                ],
                [
                    'id' => 93,
                    'name' => 'New Customer',
                    'description' => '',
                    'category' => [
                        'id' => 1,
                        'name' => 'Customer Tags',
                        'description' => null,
                    ],
                ],
                [
                    'id' => 92,
                    'name' => 'New Lead',
                    'description' => '',
                    'category' => [
                        'id' => 4,
                        'name' => 'Prospect Tags',
                        'description' => null,
                    ],
                ],
                [
                    'id' => 91,
                    'name' => 'Nurture Subscriber',
                    'description' => '',
                    'category' => [
                        'id' => 10,
                        'name' => 'Nurture Tags',
                        'description' => null,
                    ],
                ],
                [
                    'id' => 103,
                    'name' => 'test',
                    'description' => 'Auto-created Fri Aug 06 06:12:43 EDT 2021',
                    'category' => null,
                ],
            ];

        $groupMember = $this->groupMemberSetUp();

        $responseMock = $this->createMock(ClientResponse::class);
        $responseMock->method('status')->willReturn(Response::HTTP_OK);
        $responseMock->method('json')->willReturn($json);

        Http::shouldReceive('withHeaders')
            ->withArgs([
                [
                    'Content-Type' => 'application/json',
                ]
            ])
            ->andReturnSelf()
            ->shouldReceive('get')
            ->withArgs([
                $this->serviceUrl . 'crm/rest/v1/tags?access_token=' . $this->requestData->accessToken,
            ])
            ->andReturn($responseMock);

        $response = InfusionSoftService::getTags($groupMember->group_id);

        $this->assertEquals($response['code'], Response::HTTP_OK);
        $this->assertEquals($response['tags'], $json['tags']);
    }

    /**
     * @test
     * that getTags returns bad request response from InfusionSoft integration
     *
     * @covers ::getTags
     */
    public function getTags_withRequestData_returnsBadRequestResponse()
    {
        $groupMember = $this->groupMemberSetUp();

        Http::shouldReceive('withHeaders')
            ->withArgs([
                [
                    'Content-Type' => 'application/json',
                ]
            ])
            ->andReturnSelf()
            ->shouldReceive('get')
            ->withArgs([
                $this->serviceUrl . 'crm/rest/v1/tags?access_token=' . $this->requestData->accessToken,
            ])
            ->andThrows($this->mock(RequestException::class));

        $response = InfusionSoftService::getTags($groupMember->group_id);

        $this->assertEquals($response['code'], Response::HTTP_BAD_REQUEST);
    }

    /**
     * @test
     * that addTagsToContact add a tag to InfusinonSoft contact with request data and returns void
     *
     * @covers ::addTagsToContact
     */
    public function addTagsToContact_withRequestData_returnsVoid()
    {
        $infusionSoftContactId = 1039;

        $this->addTagsToContactSetUp($infusionSoftContactId, $this->requestData);

        $currentMock = $this->createMock(InfusionSoftService::class);

        TestHelper::callNonPublicFunction(
            $currentMock,
            'addTagsToContact',
            [$infusionSoftContactId, $this->requestData]
        );
    }

    /**
     * @test
     * that addTagsToContact throws a request exception while adding a tag to InfusionSoft service
     *
     * @covers ::addTagsToContact
     */
    public function addTagsToContact_withRequestData_throwsRequestException()
    {
        $infusionSoftContactId = 1039;
        $requestException = RequestException::class;

        $url = sprintf(
            '%scrm/rest/v1/contacts/%s/tags?access_token=%s',
            $this->serviceUrl,
            $infusionSoftContactId,
            $this->requestData->accessToken,
        );

        $tagIds = array_map(function ($activeTag) {
            return $activeTag->value;
        }, $this->requestData->activeTags);

        Http::shouldReceive('withHeaders')
            ->withArgs([
                [
                    'Content-Type' => 'application/json',
                ]
            ])
            ->andReturnSelf()
            ->shouldReceive('post')
            ->withArgs([
                $url,
                [
                    'tagIds' => $tagIds,
                ]
            ])
            ->andThrows($this->mock($requestException));

        $currentMock = $this->createMock(InfusionSoftService::class);

        $this->expectException($requestException);

        TestHelper::callNonPublicFunction(
            $currentMock,
            'addTagsToContact',
            [$infusionSoftContactId, $this->requestData]
        );
    }

    /**
     * @test
     * that addTagsToContact throws a InvalidState exception while adding a tag to InfusionSoft service
     *
     * @covers ::addTagsToContact
     */
    public function addTagsToContact_withRequestData_throwsInvalidStateException()
    {
        $infusionSoftContactId = 1039;
        $requestException = RequestException::class;

        $responseMock = $this->createMock(ClientResponse::class);
        $responseMock->method('status')->willReturn(Response::HTTP_BAD_REQUEST);

        $url = sprintf(
            '%scrm/rest/v1/contacts/%s/tags?access_token=%s',
            $this->serviceUrl,
            $infusionSoftContactId,
            $this->requestData->accessToken,
        );

        $tagIds = array_map(function ($activeTag) {
            return $activeTag->value;
        }, $this->requestData->activeTags);

        Http::shouldReceive('withHeaders')
            ->withArgs([
                [
                    'Content-Type' => 'application/json',
                ]
            ])
            ->andReturnSelf()
            ->shouldReceive('post')
            ->withArgs([
                $url,
                [
                    'tagIds' => $tagIds,
                ]
            ])
            ->andReturn($responseMock);

        $currentMock = $this->createMock(InfusionSoftService::class);

        $this->expectException(InvalidStateException::class);
        $this->expectExceptionMessage(GroupMembers::RESPONSE_STATUSES['FAILED_TAGS']);

        TestHelper::callNonPublicFunction(
            $currentMock,
            'addTagsToContact',
            [$infusionSoftContactId, $this->requestData]
        );
    }
}
