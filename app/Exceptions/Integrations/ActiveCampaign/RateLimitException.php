<?php

namespace App\Exceptions\Integrations\ActiveCampaign;

use App\Exceptions\Integrations\AbstractIntegrationException;
use App\GroupMembers;

/**
 * Indicates that active campaign rate limit exceeded
 *
 * @package App\Exceptions
 */
class RateLimitException extends AbstractIntegrationException
{
    /**
     * Gets the error message for this exception
     *
     * @return string containing active campaign request failed due to rate limit error message
     */
    public function getResponseStatus(): string
    {
        return GroupMembers::RESPONSE_STATUSES['ACTIVE_CAMPAIGN_RATE_LIMIT_EXCEEDED'];
    }
}
