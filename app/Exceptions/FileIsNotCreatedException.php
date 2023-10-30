<?php

namespace App\Exceptions;

use Exception;

/**
 * Fires on custom check if the file has not been created
 *
 * @package App\Exceptions
 */
class FileIsNotCreatedException extends Exception
{
    /**
     * Gets response status when exception has been fired
     *
     * @return string message for file creation failure
     */
    public function getResponseStatus(): string
    {
        return __('File is not created');
    }
}
