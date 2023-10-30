<?php

namespace Tests\Unit\app\Http\Controllers\API;

use App\Services\MarketingAutomation\InfusionSoftService;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

/**
 * Class InfusionSoftControllerTest adds test coverage for {@see \App\Http\Controllers\Api\InfusionSoftController} class
 *
 * @package Tests\Unit\app\Http\Controllers\API
 * @coversDefaultClass \App\Http\Controllers\Api\InfusionSoftController
 */
class InfusionSoftControllerTest extends TestCase
{
    use DatabaseMigrations;
    use DatabaseTransactions;

    /**
     * @var array|string[] $requestData contains client id, client secret, authorize code.
     */
    private array $requestData;

    /**
     * Setup test dependencies
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAsApiUser();

        $this->requestData = [
            'clientId' => 'GQiDUThAmipo29AjA0rQxdItoSuNxKR3',
            'clientSecret' => 'DG8V3ybtlYDtngjf',
            'authorizeCode' => 'Py5YYtGq',
        ];
    }

    /**
     * @test
     * that verifyCredentials returns validation message according to the provided request parameters.
     *
     * @covers ::verifyCredentials
     *
     * @dataProvider verifyCredentials_withVariousRequestDataProvider
     *
     * @param array $requestData containing key value pair params
     * @param string $expectedMessage contained in the response JSON
     */
    public function verifyCredentials_withVariousRequestData_returnsValidationErrorMessage(
        array $requestData,
        string $expectedMessage
    ) {
        $response = $this->call('POST', "/api/infusionSoft", $requestData);

        $response->assertStatus(Response::HTTP_BAD_REQUEST);
        $response->assertJsonStructure(['message', 'data']);
        $response->assertJsonFragment([
            'message' => $expectedMessage,
            'data' => [],
        ]);
    }

    /**
     * Data provider for {@see verifyCredentials_withVariousRequestData_returnsValidationErrorMessage}
     *
     * @return array[] containing request data with client id, client secret, authorize code.
     * and expected message of the tested method call
     */
    public function verifyCredentials_withVariousRequestDataProvider()
    {
        return [
            # Validation test cases for verifyCredentials method
            'client id is required for verifyCredentials method' => [
                'requestData' => [
                    'clientId' => '',
                    'clientSecret' => 'DG8V3ybtlYDtngjf',
                    'authorizeCode' => '312bjkhjdjdslkdjsakd2dsadasda',
                ],
                'expectedMessage' => 'The client id field is required.',
            ],
            'client secret is required for verifyCredentials method' => [
                'requestData' => [
                    'clientId' => 'GQiDUThAmipo29AjA0rQxdItoSuNxKR3',
                    'clientSecret' => '',
                    'authorizeCode' => '312bjkhjdjdslkdjsakd2dsadasda',
                ],
                'expectedMessage' => 'The client secret field is required.',
            ],
            'authorize code is required for verifyCredentials method' => [
                'requestData' => [
                    'clientId' => 'GQiDUThAmipo29AjA0rQxdItoSuNxKR3',
                    'clientSecret' => 'DG8V3ybtlYDtngjf',
                    'authorizeCode' => '',
                ],
                'expectedMessage' => 'The authorize code field is required.',
            ],
        ];
    }

    /**
     * @test
     * that verifyCredentials verify user's information (client id, client secret, authorize code)
     * from Infusionsoft integration.
     *
     * @covers ::verifyCredentials
     */
    public function verifyCredentials_happyPath_returnsSuccessResponse()
    {
        $verifyCredentialsSuccessResults = [
            'success' => true,
            'message' => 'Verification completed successfully.',
            'code' => Response::HTTP_OK,
            'body' => (object)[
                'scope' => 'full|xmk779.infusionsoft.com',
                'access_token' => 'eUGsnd7l6ONWuNRE7AzCYvsA6B0T',
                'token_type' => 'bearer',
                'expires_in' => 86399,
                'refresh_token' => 'AR0PeQHFfd5lxl07jOXkOXJAGpKnA3JL',
            ],
        ];

        $this->mock(InfusionSoftService::class)
            ->shouldReceive('requestAccessToken')
            ->withArgs([
                $this->requestData['clientId'],
                $this->requestData['clientSecret'],
                $this->requestData['authorizeCode'],
            ])
            ->andReturn($verifyCredentialsSuccessResults);

        $response = $this->postJson(route('infusionSoft', $this->requestData));

        $response->assertOk();
        $response->assertJsonStructure(['message', 'accessToken', 'refreshToken']);
        $response->assertJsonFragment([
            'message' => $verifyCredentialsSuccessResults['message'],
            'accessToken' => $verifyCredentialsSuccessResults['body']->access_token,
            'refreshToken' => $verifyCredentialsSuccessResults['body']->refresh_token,
        ]);
    }

    /**
     * @test
     * that verifyCredentials returns error response when
     * {@see \App\Services\MarketingAutomation\InfusionSoftService::requestAccessToken} method
     *
     * returns {@see Response::HTTP_BAD_REQUEST} code
     *
     * @covers ::verifyCredentials
     */
    public function verifyCredentials_whenRequestAccessTokenBodyIsNotPresent_returnsErrorResponse()
    {
        $verifyCredentialsErrorResults = [
            'success' => false,
            'message' => 'Invalid Request',
            'code' => Response::HTTP_BAD_REQUEST,
        ];

        $this->mock(InfusionSoftService::class)
            ->shouldReceive('requestAccessToken')
            ->withArgs([
                $this->requestData['clientId'],
                $this->requestData['clientSecret'],
                $this->requestData['authorizeCode']
            ])
            ->andReturn($verifyCredentialsErrorResults);

        $response = $this->postJson(route('infusionSoft', $this->requestData));

        $response->assertStatus(Response::HTTP_BAD_REQUEST);
        $response->assertJsonStructure(['message']);
        $response->assertJsonFragment(['message' => 'Invalid Request']);
    }

    /**
     * @test
     * that handleProviderCallback always returns infusionSoftCallback view
     *
     * @covers ::handleProviderCallback
     */
    public function handleProviderCallback_always_returnsInfusionSoftCallbackView()
    {
        $response = $this->get(route('infusionSoftAuthCallback'));

        $response->assertOk();
        $response->assertViewIs('infusionSoftCallback');
    }

    /**
     * @test
     * that getTags returns array of available client tags and success response
     *
     * @covers ::getTags
     */
    public function getTags_happyPath_returnsSuccessResponse()
    {
        $facebookGroupId = 34;
        $message = 'Success';
        $status = Response::HTTP_OK;

        $tags = [
            [
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

        $expectedResults = [
            'message' => $message,
            'tags' => $tags,
        ];

        $this->mock(InfusionSoftService::class)
            ->shouldReceive('getTags')
            ->withArgs([
                $facebookGroupId
            ])
            ->andReturn([
                'message' => $message,
                'code' => $status,
                'tags' => $tags,
            ]);

        $response = $this->get(route('getTags', ['facebookGroupId' => $facebookGroupId]));

        $response->assertOk();
        $response->assertJsonStructure(array_keys($expectedResults));
        $response->assertJsonFragment($expectedResults);
    }

    /**
     * @test
     * that getTags returns error response with a bad request from InfusionSoft integration
     *
     * @covers ::getTags
     */
    public function getTags_withBadRequest_returnsErrorResponse()
    {
        $facebookGroupId = 34;
        $message = 'Invalid Request';
        $status = Response::HTTP_BAD_REQUEST;

        $this->mock(InfusionSoftService::class)
            ->shouldReceive('getTags')
            ->withArgs([
                $facebookGroupId,
            ])
            ->andReturn([
                'message' => $message,
                'code' => $status,
            ]);

        $response = $this->get(route('getTags', ['facebookGroupId' => $facebookGroupId]));

        $response->assertStatus($status);
        $response->assertJsonStructure(['message']);
        $response->assertJsonFragment(['message' => $message]);
    }
}
