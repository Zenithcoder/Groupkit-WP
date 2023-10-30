<?php

namespace App\Exceptions\Integrations\ActiveCampaign;

use App\Exceptions\Integrations\AbstractIntegrationException;
use App\GroupMembers;

/**
 * Indicates that active campaign request is unprocessable.
 *
 * @package App\Exceptions
 */
class RequestUnprocessableException extends AbstractIntegrationException
{
    /**
     * Gets the error message for this exception
     *
     * @return string containing active campaign request is unprocessable error message
     */
    public function getResponseStatus(): string
    {
        return GroupMembers::RESPONSE_STATUSES['ACTIVE_CAMPAIGN_REQUEST_UNPROCESSABLE'];
    }
}
