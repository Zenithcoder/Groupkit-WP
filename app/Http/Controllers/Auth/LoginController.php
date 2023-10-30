<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Traits\GroupkitControllerBehavior;
use App\Providers\RouteServiceProvider;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use App\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Session;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Login Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles authenticating users for the application and
    | redirecting them to your home screen. The controller uses a trait
    | to conveniently provide its functionality to your applications.
    |
    */
    use AuthenticatesUsers;
    use GroupkitControllerBehavior;

    /**
     * Where to redirect users after login.
     *
     * @var string
     */
    protected $redirectTo = RouteServiceProvider::HOME;

    /**
     * Sets the middleware for this controller
     */
    protected function init()
    {
        $this->middleware('guest')->except('logout', 'autoLogin', 'appLogout', 'verifyEmail');
    }

    /**
     * Used for auto login feature from extension to webApp
     *
     * @param string $token for encode token
     *
     * @return \Illuminate\Http\Response return success if session is set, else return error message
     */
    public function autoLogin(string $token)
    {
        if ($token) {
            $tokens = base64_decode($token, true);
            $tokens = json_decode($tokens);
            if ($tokens->user) {
                $id = $tokens->user->id;
                $user = User::where('id', $id)->first();
                Auth::login($user);

                $accessToken = $user->createToken($tokens->user->email)->accessToken;
                $userDetails = $user->getDetailsByUser($user);
                /** user */
                $response = ['user' => $userDetails, 'access_token' => $accessToken];
                \Session::put('groupkit_auth', base64_encode(json_encode($response)));

                return response(['message' => __('The user is logged in')]);
            }
        }

        return response(['message' => __('The user is not logged in')], Response::HTTP_BAD_REQUEST);
    }

    /**
     * Returns set password view with provided email and token
     *
     * @param string $email of the user who will set the password
     * @param string $token for a current user request authorization
     *
     * @return View set password for setting the password for new users
     */
    public function setPassword(string $email, string $token): View
    {
        return view('auth.passwords.setPass', compact('email', 'token'));
    }

    /**
     * Setting the user password for users who don't have a password
     *
     * @return RedirectResponse to the home route with
     *                          success message if the user password is set
     *                          or redirect to setting route with
     *                          error message
     *
     * @throws ValidationException if one of the validation rules fails
     */
    public function updatePassword(): RedirectResponse
    {
        $this->validate($this->request, [
            'token' => 'required',
            'email' => 'required|email|exists:users,email',
            'password' => 'required|min:8|confirmed',
        ]);

        $user = User::where('email', $this->request->email)->first();

        // Check if the user already has a set password
        if(!empty($user->password)){
            return redirect()->back()->withErrors(['email' => __('The password is already set')]);
        }

        $status = Password::reset(
            $this->request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, $password) {
                $user->forceFill(['password' => Hash::make($password)])->setRememberToken(Str::random(60));
                $user->save();
            }
        );

        // Redirect the user back if the reset attempt did not succeed
        if ($status !== Password::PASSWORD_RESET) {
            return redirect()->back()->withErrors(['email' => [__($status)]]);
        }

        Auth::login($user);

        /** Set Session */
        $accessToken = $user->createToken($user->email)->accessToken;
        $response = ['user' => $user, 'access_token' => $accessToken];
        \Session::put('groupkit_auth', base64_encode(json_encode($response)));
        return redirect('home');
    }

    public function login()
    {
        $user = User::where('email', $this->request->email)->first();

        # when email is not in our system
        if (!$user) {
            return redirect()->back()->withInput()
                ->withErrors(['email' => 'Please try again! Those credentials do not match our records.']);
        }

        # when account is not activated
        if (!$user->status) {
            return redirect()->back()->withInput()->withErrors(array('email' => 'Your account is not active.'));
        }

        # when the user's password has not yet been set in our system
        if (!@$user->password) {
            $email = $this->request->input('email');
            $token = Password::createToken($user);

            return redirect("setPassword/$email/$token");
        }

        # login if password matches, or redirect with credential error
        if (
            Auth::attempt(
                ['email' => $this->request->email, 'password' => $this->request->password],
                $this->request->filled('remember')
            )
        ) {
            $accessToken = $user->createToken($this->request->email)->accessToken;
            /** user */
            $user->plan_name = $user->subscriptionsPlan($user->id);
            $user->access_team = (bool)$user->activePlan();

            $userDetails = $user->getDetailsByUser($user);
            $response = ['user' => $userDetails, 'access_token' => $accessToken];
            \Session::put('groupkit_auth', base64_encode(json_encode($response)));

            return redirect('/');
        } else {
            return redirect()->back()->withInput()
                ->withErrors(
                    array('email' => 'Please try again! Those credentials do not match our records.')
                );
        }
    }

    public function verifyEmail()
    {
        $user = User::where('email', $this->request->email)->first();
        # when email is not in our system
        if (!$user) {
            return response(
                [
                    'status' => 'error',
                    'message' => __('Please try again! Those credentials do not match our records.'),
                ],
                200
            );
        }

        # when account is not activated
        if (!$user->status) {
            return response(['status' => 'error', 'message' => __('Your account is not active.')]);
        }

        # when the user's password has not yet been set in our system
        if (!@$user->password) {
            $token = Password::createToken($user);

            return response([
                'status' => 'error',
                'message' => __('Redirect'),
                'data' => "/setPassword/{$this->request->email}/{$token}",
            ]);
        }

        # email was verified to be in our system with a password
        return response(['status' => 'success', 'message' => __('Success')]);
    }

    public function appLogout($token)
    {
        if ($token) {
            $tokens = base64_decode($token, true);
            $tokens = json_decode($tokens);
            if ($tokens) {
                Auth::logout();
                Session::remove('groupkit_auth');
            }
        }
        return response(['status' => 'success', 'message' => __('Removed Token')]);
    }
}
