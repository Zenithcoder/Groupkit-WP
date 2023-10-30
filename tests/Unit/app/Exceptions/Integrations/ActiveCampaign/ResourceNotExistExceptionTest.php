<?php

namespace Tests\Unit\app\Exceptions\Integrations\ActiveCampaign;

use App\Exceptions\Integrations\ActiveCampaign\ResourceNotExistException;
use App\GroupMembers;
use Tests\TestCase;

/**
 * Class ResourceNotExistExceptionTest adds test coverage for {@see ResourceNotExistException}
 *
 * @package Tests\Unit\app\Http\Controllers\API
 * @coversDefaultClass \App\Exceptions\Integrations\ActiveCampaign\ResourceNotExistException
 */
class ResourceNotExistExceptionTest extends TestCase
{
    /**
     * @test
     * that getResponseStatus returns {@see GroupMembers::RESPONSE_STATUSES['ACTIVE_CAMPAIGN_RESOURCE_NOT_EXIST']}
     *
     * @covers ::getResponseStatus
     */
    public function getResponseStatus()
    {
        $authorizationMock = $this->partialMock(ResourceNotExistException::class);

        $response = $authorizationMock->getResponseStatus();

        $this->assertEquals(
            GroupMembers::RESPONSE_STATUSES['ACTIVE_CAMPAIGN_RESOURCE_NOT_EXIST'],
            $response
        );
    }
}
