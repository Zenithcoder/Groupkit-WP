<?php

namespace Tests\Unit\app\Exceptions\Integrations\ActiveCampaign;

use App\Exceptions\Integrations\ActiveCampaign\RequestUnprocessableException;
use App\GroupMembers;
use Tests\TestCase;

/**
 * Class RequestUnprocessableExceptionTest adds test coverage for {@see RequestUnprocessableException}
 *
 * @package Tests\Unit\app\Http\Controllers\API
 * @coversDefaultClass \App\Exceptions\Integrations\ActiveCampaign\RequestUnprocessableException
 */
class RequestUnprocessableExceptionTest extends TestCase
{
    /**
     * @test
     * that getResponseStatus returns {@see GroupMembers::RESPONSE_STATUSES['ACTIVE_CAMPAIGN_REQUEST_UNPROCESSABLE']}
     *
     * @covers ::getResponseStatus
     */
    public function getResponseStatus()
    {
        $authorizationMock = $this->partialMock(RequestUnprocessableException::class);

        $response = $authorizationMock->getResponseStatus();

        $this->assertEquals(
            GroupMembers::RESPONSE_STATUSES['ACTIVE_CAMPAIGN_REQUEST_UNPROCESSABLE'],
            $response
        );
    }
}
