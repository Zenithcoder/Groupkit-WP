<?php

namespace App\Exceptions\Integrations\ActiveCampaign;

use App\Exceptions\Integrations\AbstractIntegrationException;
use App\GroupMembers;

/**
 * Indicates that active campaign resource does not exist.
 *
 * @package App\Exceptions
 */
class ResourceNotExistException extends AbstractIntegrationException
{
    /**
     * Gets the error message for this exception
     *
     * @return string containing active campaign requested resource does not exist error message
     */
    public function getResponseStatus(): string
    {
        return GroupMembers::RESPONSE_STATUSES['ACTIVE_CAMPAIGN_RESOURCE_NOT_EXIST'];
    }
}
