<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Class StripeSyncMail sends a notification of a newly created cloud application
 * account to legacy customer who were not in the beta-release program
 *
 * @package App\Mail
 */
class StripeSyncMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    /**
     * mailData object is passed to the email template (emails.stripe-customer)
     *
     * @var object
     */
    public object $mailData;

    /**
     * Here we get the email and username of the customer.
     *
     * @param object $mailData
     *
     * @return void
     */
    public function __construct(object $mailData)
    {
        $this->mailData = $mailData;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build(): StripeSyncMail
    {
        return $this->subject('Your GroupKit Login Details')
            ->markdown('emails.stripe-customer');
    }
}
