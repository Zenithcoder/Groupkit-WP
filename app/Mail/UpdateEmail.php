<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Class UpdateEmail sends an activation code in the email to the user's newly given email address.
 *
 * @package App\Mail
 */
class UpdateEmail extends Mailable
{
    use Queueable;
    use SerializesModels;

    /**
     * @var string activation code for changing email address
     */
    public string $activationCode;

    /**
     * @var string name of the customer that wants to change the email address
     */
    public string $customerName;

    /**
     * Sets internal properties
     *
     * @param string $customerName of the customer that wants to change the email address
     * @param string $activationCode for changing email address
     */
    public function __construct(string $customerName, string $activationCode)
    {
        $this->customerName = $customerName;
        $this->activationCode = $activationCode;
    }

    /**
     * Build the message.
     *
     * @return $this with invite subject and invite mail view
     */
    public function build()
    {
        return $this->subject('Change user email address')->markdown('emails.update-email');
    }
}
