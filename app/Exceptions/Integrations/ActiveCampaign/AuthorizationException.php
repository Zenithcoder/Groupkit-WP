<?php

namespace App\Exceptions\Integrations\ActiveCampaign;

use App\Exceptions\Integrations\AbstractIntegrationException;
use App\GroupMembers;

/**
 * Indicates that active campaign request failed due to authorization/authentication issues
 *
 * @package App\Exceptions
 */
class AuthorizationException extends AbstractIntegrationException
{
    /**
     * Gets the error message for this exception
     *
     * @return string containing active campaign request failed due to authorization/authentication error message
     */
    public function getResponseStatus(): string
    {
        return GroupMembers::RESPONSE_STATUSES['ACTIVE_CAMPAIGN_AUTHORIZATION_ISSUE'];
    }
}
