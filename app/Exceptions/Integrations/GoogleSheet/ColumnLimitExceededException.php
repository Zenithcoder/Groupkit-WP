<?php

namespace App\Exceptions\Integrations\GoogleSheet;

use App\Exceptions\Integrations\AbstractIntegrationException;
use App\GroupMembers;

/**
 * Indicates that google sheet document has more column than we support
 *
 * @package App\Exceptions
 */
class ColumnLimitExceededException extends AbstractIntegrationException
{
    /**
     * Gets google sheet column limit exceeded response status
     *
     * @return string containing google sheet column limit exceeded response status
     */
    public function getResponseStatus(): string
    {
        return GroupMembers::RESPONSE_STATUSES['G_SHEET_COLUMN_LIMIT_EXCEEDED'];
    }
}
