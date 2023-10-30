<?php

namespace App\Notifications;

use App\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\URL;
use Carbon\Carbon;

class VerifyUserMail extends Notification
{
    use Queueable;

    /**
     * @var string name of the registered {@see User}
     */
    public $userName;

    /**
     * Create a new notification instance.
     *
     * @param  string  $userName of the registered {@see User}
     */
    public function __construct(string $userName)
    {
        $this->userName = $userName;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param  mixed  $notifiable delivered request
     *
     * @return array containing mail string
     */
    public function via($notifiable)
    {
        return ['mail'];
    }

    /**
     * Generate temporary verification url.
     *
     * @param  mixed  $notifiable delivered request
     *
     * @return string signed route
     */
    protected function verificationUrl($notifiable)
    {
        return URL::temporarySignedRoute(
            'verification.verify',
            Carbon::now()->addMinutes(Config::get('auth.verification.expire', 60)),
            [
                'id' => $notifiable->getKey(),
                'hash' => sha1($notifiable->getEmailForVerification()),
            ]
        );
    }

    /**
     * Get the mail representation of the notification.
     *
     * @param  mixed  $notifiable delivered request
     *
     * @return MailMessage instance containing name of the user, verification URL and mail text
     */
    public function toMail($notifiable)
    {
        return (new MailMessage())
            ->greeting('Hello ' . $this->userName . '!')
            ->line('Please click the button below to verify your email address and activate your GroupKit account.')
            ->action('Verify Email Address', $this->verificationUrl($notifiable))
            ->line('If you did not create a GroupKit account, no further action is required.');
    }
}
