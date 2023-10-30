<?php

namespace Tests\Unit\app\Http\Controllers\API;

use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

/**
 * Class OntraPortControllerTest adds test coverage for {@see \App\Http\Controllers\Api\OntraPortController} class
 *
 * @package Tests\Unit\app\Http\Controllers\API
 * @coversDefaultClass \App\Http\Controllers\Api\OntraPortController
 */
class OntraPortControllerTest extends TestCase
{
    use DatabaseMigrations;
    use DatabaseTransactions;

    /**
     * @test
     * that verifyCredentials verify user's information (app_id,app_key) from OntraPort integration
     *
     * @covers ::verifyCredentials
     */
    public function verifyCredentials_withListData_returnsData()
    {
        $this->actingAsApiUser();

        $requestData = [
            'app_id' => '2_223001_1TfU4OIX0',
            'app_key' => 'aEVQ6HeNYS4fpdF',
        ];

        $verifyCredentialsResults = [
            'success' => true,
            'message' => 'Verification completed successfully',
            'code' => Response::HTTP_OK,
        ];

        $this->mock(OntraPortService::class)
            ->shouldReceive('verifyCredentials')
            ->withArgs([$requestData['app_key'], $requestData['app_id']])
            ->andReturn($verifyCredentialsResults);

        $response = $this->postJson(route('ontraPort', $requestData));

        $response->assertOk();
        $response->assertJsonStructure(['message']);
        $response->assertJsonFragment(['message' => 'Verification completed successfully']);
    }

    /**
     * @test
     * that verifyCredentials verify user's information (app_id,app_key) from OntraPort integration
     * and returns exception message if passed wrong request data.
     *
     * @covers ::verifyCredentials
     */
    public function verifyCredentials_withWrongRequestData_returnsExeceptionMessage()
    {
        $this->actingAsApiUser();

        $requestData = [
            'app_id' => '2_223001_1TfU4OIX9', #passed wrong app_id
            'app_key' => 'aEVQ6HeNYS4fpdF',
        ];

        $verifyCredentialsResults = [
            'success' => false,
            'message' => 'Invalid Request',
            'code' => Response::HTTP_BAD_REQUEST,
        ];

        $this->mock(OntraPortService::class)
            ->shouldReceive('verifyCredentials')
            ->withArgs([$requestData['app_key'], $requestData['app_id']])
            ->andReturn($verifyCredentialsResults);

        $response = $this->postJson(route('ontraPort', $requestData));
        $response->assertStatus(Response::HTTP_BAD_REQUEST);
        $response->assertJsonStructure(['message']);
        $response->assertJsonFragment(['message' => 'Invalid Request']);
    }
}
