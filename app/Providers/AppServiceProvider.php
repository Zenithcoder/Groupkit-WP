<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Schema;

use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\URL;
use Carbon\Carbon;
use App\User;
use Illuminate\Support\Facades\Config;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        //
        Schema::defaultStringLength(191);

        // Override the email notification for verifying email
        VerifyEmail::toMailUsing(function ($notifiable) {
            $verifyUrl = URL::temporarySignedRoute(
                'verification.verify',
                Carbon::now()->addMinutes(Config::get('auth.verification.expire', 60)),
                [
                    'id' => $notifiable->getKey(),
                    'hash' => sha1($notifiable->getEmailForVerification()),
                ]
            );

            $user = User::whereEmail($notifiable->getEmailForVerification())->first();
            return (new MailMessage())
                ->subject('Verify Email Address')
                ->markdown('emails.verify-email', ['user' => $user, 'verifyUrl' => $verifyUrl]);
        });

        // TODO: send application version and additional application-specific (global) data to Bugsnag
        // https://docs.bugsnag.com/platforms/php/laravel/#tracking-releases
        // https://docs.bugsnag.com/platforms/php/laravel/#custom-diagnostics
    }
}
