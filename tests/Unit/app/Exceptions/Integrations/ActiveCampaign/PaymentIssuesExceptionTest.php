<?php

namespace Tests\Unit\app\Exceptions\Integrations\ActiveCampaign;

use App\Exceptions\Integrations\ActiveCampaign\PaymentIssuesException;
use App\GroupMembers;
use Tests\TestCase;

/**
 * Class PaymentIssuesExceptionTest adds test coverage for {@see PaymentIssuesException}
 *
 * @package Tests\Unit\app\Http\Controllers\API
 * @coversDefaultClass \App\Exceptions\Integrations\ActiveCampaign\PaymentIssuesException
 */
class PaymentIssuesExceptionTest extends TestCase
{
    /**
     * @test
     * that getResponseStatus returns {@see GroupMembers::RESPONSE_STATUSES['ACTIVE_CAMPAIGN_PAYMENT_ISSUE']}
     *
     * @covers ::getResponseStatus
     */
    public function getResponseStatus()
    {
        $authorizationMock = $this->partialMock(PaymentIssuesException::class);

        $response = $authorizationMock->getResponseStatus();

        $this->assertEquals(
            GroupMembers::RESPONSE_STATUSES['ACTIVE_CAMPAIGN_PAYMENT_ISSUE'],
            $response
        );
    }
}
