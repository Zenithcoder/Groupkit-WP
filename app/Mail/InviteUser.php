<?php

namespace App\Mail;

use App\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class InviteUser extends Mailable
{
    use Queueable;
    use SerializesModels;

     /**
     * @var User to be notify about login information
     */
    public User $user;

    /**
     * Create a new message instance.
     *
     * @param User $user newly registered
     */
    public function __construct(User $user)
    {
        $this->user = $user;
    }

    /**
     * Build the message.
     *
     * @return $this with invite subject and invite mail view
     */
    public function build()
    {
        return $this->subject('You have Been Invited To GroupKit (logins inside)')->markdown('emails.invite-user');
    }
}
