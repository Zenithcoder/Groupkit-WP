<?php

namespace App\Exceptions\Stripe;

use Exception;
use App\User;

/**
 * Indicates that Stripe ID already exists in the user table
 *
 * @package App\Exceptions
 */
class StripeIDAlreadyExistsException extends Exception
{
    /**
     * Gets user response status for Stripe ID already exists
     *
     * @return string Stripe ID already exists response status
     */
    public function getResponseStatus(): string
    {
        return User::RESPONSE_STATUSES['STRIPE_ID_ALREADY_EXISTS'];
    }
}
