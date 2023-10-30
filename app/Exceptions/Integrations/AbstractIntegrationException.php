<?php

namespace App\Exceptions\Integrations;

use App\GroupMembers;
use Exception;

/**
 * Class AbstractIntegrationException is base class for all integration exceptions
 *
 * @package App\Exceptions\Integrations
 */
abstract class AbstractIntegrationException extends Exception
{
    /**
     * Gets default error response status
     *
     * @return string
     */
    public function getResponseStatus(): string
    {
        return GroupMembers::RESPONSE_STATUSES['ERROR'];
    }
}
