<?php

namespace Tests\Unit\app\Exceptions\Integrations\ActiveCampaign;

use App\Exceptions\Integrations\ActiveCampaign\RateLimitException;
use App\GroupMembers;
use Tests\TestCase;

/**
 * Class RateLimitExceptionTest adds test coverage for {@see RateLimitException}
 *
 * @package Tests\Unit\app\Http\Controllers\API
 * @coversDefaultClass \App\Exceptions\Integrations\ActiveCampaign\RateLimitException
 */
class RateLimitExceptionTest extends TestCase
{
    /**
     * @test
     * that getResponseStatus returns {@see GroupMembers::RESPONSE_STATUSES['ACTIVE_CAMPAIGN_RATE_LIMIT_EXCEEDED']}
     *
     * @covers ::getResponseStatus
     */
    public function getResponseStatus()
    {
        $authorizationMock = $this->partialMock(RateLimitException::class);

        $response = $authorizationMock->getResponseStatus();

        $this->assertEquals(
            GroupMembers::RESPONSE_STATUSES['ACTIVE_CAMPAIGN_RATE_LIMIT_EXCEEDED'],
            $response
        );
    }
}
