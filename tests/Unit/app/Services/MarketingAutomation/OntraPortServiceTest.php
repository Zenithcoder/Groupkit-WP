<?php

namespace Tests\Unit\app\Services\MarketingAutomation;

use App\AutoResponder;
use App\Exceptions\InvalidStateException;
use App\FacebookGroups;
use App\GroupMembers;
use App\Services\MarketingAutomation\AbstractMarketingService;
use App\Services\MarketingAutomation\OntraPortService;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use ReflectionException;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;
use Tests\TestHelper;

/**
 * Class IntegrationServiceTest adds test coverage for {@see OntraPortService}
 *
 * @package Tests\Unit\app\Services\MarketingAutomation
 * @coversDefaultClass \App\Services\MarketingAutomation\OntraPortService
 */
class OntraPortServiceTest extends TestCase
{
    use DatabaseTransactions;
    use DatabaseMigrations;

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
     * @test
     * that index returns single user's information from OntraPort integration
     *
     * @covers ::subscribe
     */
    public function subscribe_withListData_returnVoid()
    {
        $user = $this->actingAsUser();

        $facebookGroup = FacebookGroups::factory()->create();

        $groupMember = GroupMembers::factory()->create([
            'f_name' => 'may',
            'l_name' => 'day',
            'a1' => 'Question 1 desc',
            'a2' => 'Question 2 desc',
            'a3' => 'Question 3 desc',
            'user_id' => $user->id,
            'group_id' => $facebookGroup->id,
            'respond_status' => null,
            'deleted_at' => null,
            'email' => $user->email,
        ]);

        AutoResponder::factory()->create([
            'responder_type' => 'OntraPort',
            'responder_json' => json_encode([
                'app_id' => '2_223001_1TfU4OIX0',
                'app_key' => 'aEVQ6HeNYS4fpdF',
            ]),
            'user_id' => $user->id,
            'group_id' => $facebookGroup->id,
        ]);
        $response = app(OntraPortService::class)->subscribe($groupMember);
        $this->assertNull($response);
    }

    /**
     * @test
     * that add Or Update Contact on OntraPort integration and returns true if contact merged to ontraport side
     * otherwise return false throws Invalid State Exception
     *
     * @covers ::addOrUpdateContact
     */
    public function addOrUpdateContact_withContactData_returnsTrue()
    {
        $user = $this->actingAsUser();

        $facebookGroup = FacebookGroups::factory()->create();

        $groupMember = GroupMembers::factory()->create([
            'f_name' => 'may',
            'l_name' => 'day',
            'a1' => 'Question 1 desc',
            'a2' => 'Question 2 desc',
            'a3' => 'Question 3 desc',
            'user_id' => $user->id,
            'group_id' => $facebookGroup->id,
            'respond_status' => null,
            'deleted_at' => null,
            'email' => $user->email,
        ]);

        $extraParameters = (object)[
            'app_id' => '2_223001_1TfU4OIX0',
            'app_key' => 'aEVQ6HeNYS4fpdF',
        ];

        $currentMock = $this->createMock(OntraPortService::class);

        $response = TestHelper::callNonPublicFunction(
            $currentMock,
            'addOrUpdateContact',
            [$groupMember, $extraParameters]
        );
        $this->assertNull($response);
    }

    /**
     * @test
     * that index verify user's information(application key & application id) with OntraPort integration
     *
     * @covers ::verifyCredentials
     */
    public function verifyCredentials_withUsersData_returnSuccessResponse()
    {
        $appKey = 'aEVQ6HeNYS4fpdF';
        $appId = '2_223001_1TfU4OIX0';

        $response = app(OntraPortService::class)->verifyCredentials($appKey, $appId);

        $this->assertEquals(true, $response['success']);
        $this->assertEquals('Verification completed successfully', $response['message']);
        $this->assertEquals(Response::HTTP_OK, $response['code']);
    }

    /**
     * @test
     * that index verify user's information(application key & application id) with OntraPort integration
     * and return error response as passed details are wrong
     *
     * @covers ::verifyCredentials
     */
    public function verifyCredentials_withUsersData_returnErrorResponse()
    {
        $appKey = 'aEVQ6HeNYS4fpdF1';
        $appId = '2_223001_1TfU4OIX0';

        $response = app(OntraPortService::class)->verifyCredentials($appKey, $appId);

        $this->assertEquals(false, $response['success']);
        $this->assertEquals('Invalid Request', $response['message']);
        $this->assertEquals(Response::HTTP_BAD_REQUEST, $response['code']);
    }

    /**
     * @test
     * that index verify user's information(application key & application id) with OntraPort integration
     * and return error response
     *
     * @covers ::verifyCredentials
     */
    public function verifyCredentials_withUsersData_returnDifferentStatusResponse()
    {
        $appKey = 'aEVQ6HeNYS4fpdF';
        $appId = '2_223001_1TfU4OIX0';

        $verifyCredentialsResults = [
            'success' => false,
            'message' => 'Invalid Request',
            'code' => Response::HTTP_BAD_REQUEST,
        ];

        $this->mock(OntraPortService::class)
            ->shouldReceive('verifyCredentials')
            ->withArgs([$appKey, $appId])
            ->andReturn($verifyCredentialsResults);

        $response = app(OntraPortService::class)->verifyCredentials($appKey, $appId);

        $this->assertEquals(false, $response['success']);
        $this->assertEquals('Invalid Request', $response['message']);
        $this->assertEquals(Response::HTTP_BAD_REQUEST, $response['code']);
    }

    /**
     * @test
     * that add Or Update Contact on OntraPort integration throws Invalid State Exception
     *
     * @covers ::addOrUpdateContact
     *
     * @throws ReflectionException
     */
    public function addOrUpdateContact_withoutListData_returnsInvalidStateException()
    {
        $user = $this->actingAsUser();

        $facebookGroup = FacebookGroups::factory()->create(['user_id' => $user->id]);

        $groupMember = GroupMembers::factory()->create([
            'f_name' => 'may',
            'l_name' => 'day',
            'a1' => 'Question 1 desc',
            'a2' => 'Question 2 desc',
            'a3' => 'Question 3 desc',
            'user_id' => $user->id,
            'group_id' => $facebookGroup->id,
            'respond_status' => null,
            'deleted_at' => null,
            'email' => $user->email,
        ]);

        $extraParameter = (object)[
            'app_id' => '2_223001_1TfU4OIX0111',
            'app_key' => 'aEVQ6HeNYS4fpdF',
        ];

        $currentMock = $this->createMock(OntraPortService::class);

        $clientException = $this->createMock(InvalidStateException::class);

        $clientMock = $this->mock(Client::class);

        $clientMock->shouldReceive('post')
            ->withSomeOfArgs('https://api.ontraport.com/1/Contacts/saveorupdate')
            ->andThrow($clientException);

        $this->app->bind(Client::class, function () use ($clientMock) {
            return $clientMock;
        });

        $this->expectException(InvalidStateException::class);
        $this->expectExceptionMessage('Not Added');

        TestHelper::callNonPublicFunction(
            $currentMock,
            'addOrUpdateContact',
            [$groupMember, $extraParameter]
        );
    }
}
