<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Traits\GroupkitControllerBehavior;
use App\Providers\RouteServiceProvider;
use App\Services\TapfiliateService;
use App\User;
use Illuminate\Foundation\Auth\RegistersUsers;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class RegisterController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Register Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles the registration of new users as well as their
    | validation and creation. By default this controller uses a trait to
    | provide this functionality without requiring any additional code.
    |
    */
    use RegistersUsers;
    use GroupkitControllerBehavior;

    /**
     * Where to redirect users after registration.
     *
     * @var string
     */
    protected $redirectTo = RouteServiceProvider::HOME;

    /**
     * Sets the middleware for this controller
     */
    protected function init()
    {
        $this->middleware('guest');
    }

    /**
     * Get a validator for an incoming registration request.
     *
     * @param  array  $data
     * @return \Illuminate\Contracts\Validation\Validator
     */
    protected function validator(array $data)
    {
        return Validator::make($data, [
            'first_name' => ['required', 'string', 'max:32', 'regex:/^[a-zA-Z\-\'\,]+$/u'],
            'last_name' => ['required', 'string', 'max:32', 'regex:/^[a-zA-Z\s\-\'\,]+$/u'],
            'email' => ['required', 'string', 'email', 'max:100', 'unique:users,email', 'regex:/(.+)@(.+)\.(.+)/i'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);
    }

    /**
     * Create a new user instance after a valid registration.
     *
     * @param  array  $data
     * @return \App\User
     */
    protected function create(array $data)
    {
        $user = User::create([
            'name' => $data['first_name'] . ' ' . $data['last_name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
        ]);
        $accessToken = $user->createToken($user->email)->accessToken;
        $response = ['user' => $user, 'access_token' => $accessToken];
        \Session::put('groupkit_auth', base64_encode(json_encode($response)));
        return $user;
    }

    /**
     * Redirects a guest to the plans page as the first step in the registration process
     *
     * @return RedirectResponse to the plans page
     */
    public function showRegistrationForm()
    {
        if ($this->request->input(TapfiliateService::TAPFILIATE_REQUEST_PARAMETER)) {
            TapfiliateService::storeTapfiliateCookie(
                $this->request->input(TapfiliateService::TAPFILIATE_REQUEST_PARAMETER)
            );
        }
        return redirect('plans');
    }
}
