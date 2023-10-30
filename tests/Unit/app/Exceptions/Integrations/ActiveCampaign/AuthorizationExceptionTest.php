<?php

namespace Tests\Unit\app\Exceptions\Integrations\ActiveCampaign;

use App\Exceptions\Integrations\ActiveCampaign\AuthorizationException;
use App\GroupMembers;
use Tests\TestCase;

/**
 * Class AuthorizationExceptionTest adds test coverage for {@see AuthorizationException}
 *
 * @package Tests\Unit\app\Http\Controllers\API
 * @coversDefaultClass \App\Exceptions\Integrations\ActiveCampaign\AuthorizationException
 */
class AuthorizationExceptionTest extends TestCase
{
    /**
     * @test
     * that getResponseStatus returns {@see GroupMembers::RESPONSE_STATUSES['ACTIVE_CAMPAIGN_AUTHORIZATION_ISSUE']}
     *
     * @covers ::getResponseStatus
     */
    public function getResponseStatus()
    {
        $authorizationMock = $this->partialMock(AuthorizationException::class);

        $response = $authorizationMock->getResponseStatus();

        $this->assertEquals(
            GroupMembers::RESPONSE_STATUSES['ACTIVE_CAMPAIGN_AUTHORIZATION_ISSUE'],
            $response
        );
    }
}
