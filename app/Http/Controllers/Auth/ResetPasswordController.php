<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Traits\GroupkitControllerBehavior;
use App\Providers\RouteServiceProvider;
use Illuminate\Foundation\Auth\ResetsPasswords;
use Illuminate\Routing\Controller;

class ResetPasswordController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Password Reset Controller
    |--------------------------------------------------------------------------
    |
    | This controller is responsible for handling password reset requests
    | and uses a simple trait to include this behavior. You're free to
    | explore this trait and override any methods you wish to tweak.
    |
    */
    use ResetsPasswords;
    use GroupkitControllerBehavior;

    /**
     * Where to redirect users after resetting their password.
     *
     * @var string
     */
    protected $redirectTo = RouteServiceProvider::HOME;

    protected function resetPassword($user, $password)
    {
        $user->forceFill([
            'password' => bcrypt($password),
            'remember_token' => \Str::random(60),
        ])->save();

        /** Set Session */
        $accessToken = $user->createToken($user['email'])->accessToken;
        $response = ['user' => $user, 'access_token' => $accessToken];
        \Session::put('groupkit_auth', base64_encode(json_encode($response)));
        \Auth::login($user);

        $this->guard()->login($user);
    }
}
