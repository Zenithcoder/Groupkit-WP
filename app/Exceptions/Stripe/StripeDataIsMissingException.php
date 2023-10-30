<?php

namespace App\Exceptions\Stripe;

use Exception;

/**
 * Indicates that Stripe data is missing
 *
 * @package App\Exceptions
 */
class StripeDataIsMissingException extends Exception
{
    /**
     * Gets response status for Stripe data is missing
     *
     * @return string message for Stripe data is missing
     */
    public function getResponseStatus(): string
    {
        return __('Stripe data is missing');
    }
}
