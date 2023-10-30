<?php

namespace App\Exceptions;

use Throwable;

/**
 * Indicates that the preconditions are not met in order to proceed with a process
 *
 * @package App\Exceptions
 */
class InvalidStateException extends \Exception
{
    /**
     * Additional information about the state of the exception.
     *
     * @var string
     */
    private string $additionalInformation;

    /**
     * Construct the exception. Note: The message is NOT binary safe.
     * 
     * @param string $message — [optional] The Exception message to throw.
     * @param string $additionalInformation — [optional] Additional information
     *               about the state of the exception.
     * @param int $code — [optional] The Exception code.
     * @param null|Throwable $previous - [optional] The previous throwable used
     *                       for the exception chaining.
     */
    public function __construct(
        string $message = '',
        string $additionalInformation = '',
        int $code = 0,
        ?Throwable $previous = null
    ) {
        $this->additionalInformation = $additionalInformation;
        parent::__construct($message, $code, $previous);
    }

    /**
     * Get additional information about the exception.
     *
     * @return string
     */
    public function getAdditionalInformation(): string
    {
        return $this->additionalInformation;
    }
}
