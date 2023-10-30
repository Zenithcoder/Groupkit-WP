<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Traits\GroupkitControllerBehavior;
use App\User;
use Exception;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Routing\Controller;
use Illuminate\Routing\Redirector;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;
use Illuminate\Support\Facades\Session;
use Laravel\Socialite\Facades\Socialite;
use Illuminate\Http\RedirectResponse;

/**
 * Controller for actions related to social logins
 * Currently only supports Facebook
 */
class SocialController extends Controller
{
    use GroupkitControllerBehavior;

    /** @var string[] List of supported social login providers */
    public const SUPPORTED_PROVIDERS = ['facebook'];

    /**
     * Determines if given provider is supported
     *
     * @param string $provider code to
     *
     * @return bool true if supported, otherwise false
     */
    public static function isProviderSupported(string $provider): bool
    {
        return in_array(strtolower($provider), self::SUPPORTED_PROVIDERS);
    }

    /**
     * Redirects to appropriate providers based on $provider
     *
     * @param string $provider of the social login

     * @return RedirectResponse to the appropriate social network login page if supported,
     *                                                otherwise directs the requester to the previous page
     */
    public function redirectToProvider(string $provider): RedirectResponse
    {
        return in_array($provider, self::SUPPORTED_PROVIDERS)
            ? Socialite::driver($provider)
                ->with(['state' => $this->request->plan, 'display' => $this->request->fromExtension ? 'popup' : 'page'])
                ->redirect()
            : back();
    }

    /**
     * Action user is redirected to after OAuth authorization
     * Logs in the customer in if they have an account otherwise redirects to sign up page
     * If the authenticated customer already has a regular account (based on email) he is redirected to the password
     * confirmation page
     *
     * @param string $provider representation of the social login provider {@see SUPPORTED_PROVIDERS}
     *                         provided as a route parameter in the callback by Socialite
     *                         {@see \Laravel\Socialite\Two\AbstractProvider::getCodeFields}
     *                         defined in services config for each provider
     *
     * @return RedirectResponse redirection to one of the following routes
     * 1. back to the previous page            - if authentication provider is not supported
     * 2. to the home page                     - after a successful login
     * 3. to the plans page                    - if authenticated customer doesn't already have an account
     * 4. to the password confirmation page    - if the authenticated customer already has a an account
     * 5. to the login page with error message - if an exception occurs during the callback handling
     */
    public function handleProviderCallback(string $provider)
    {
        if (!in_array($provider, self::SUPPORTED_PROVIDERS)) {
            return back();
        }

        try {
            $socialUserData = Socialite::driver($provider)->stateless()->user();

            $user = User::where(User::getUserIdFieldForSocialProvider($provider), $socialUserData->id)->first();
            $accessToken = $socialUserData->token;
            if (!$user) {
                /**
                 * If we don't have a user associated with the social network id, then
                 * search for the user by email and associate it with the social network id
                 */
                $user = User::where('email', $socialUserData->email)->first();
                if ($user && !$user->getAttribute(User::getUserIdFieldForSocialProvider($provider))) {
                    Session::put('access_token', $accessToken);
                    Session::put('access_provider', $provider);
                    return redirect(route('social.login.confirmPassword', [$provider]))
                        ->with('user', $user);
                }
            }
            if ($user) {
                $this->loginUserWithSocial($user, $provider, $accessToken);
                return redirect('/');
            } else {
                $planId = $this->request->state;
                Session::put('access_token', $accessToken);
                Session::put('access_provider', $provider);
                Session::put('first_name', substr($socialUserData->name, 0, strpos($socialUserData->name, ' ')));
                Session::put('last_name', substr($socialUserData->name, strpos($socialUserData->name, ' ') + 1));
                Session::put('email', $socialUserData->email);

                if ($planId) {
                    return redirect(route('plans.show', [$planId]));
                }
                return redirect(route('plans.index'));
            }
        } catch (Exception $e) {
            return redirect(route('login'))->withErrors([$provider . '_login' => 'Social Login failed']);
        }
    }

    /**
     * Displays the password confirmation form
     *
     * @param string $provider string representation of the social login provider {@see SUPPORTED_PROVIDERS}
     * @return Application|Factory|View|RedirectResponse|Redirector
     *      View of the social media account connection confirmation screen if the social
     *      media authorization passed and a corresponding user was found; otherwise, a
     *      redirect to the general login page
     */
    public function showPasswordConfirmation(string $provider)
    {
        if (
            (!$provider && !$this->request->session()->get('access_provider'))
            || !$this->request->session()->get('access_token')
            || !$this->request->session()->get('user')
        ) {
            return redirect('/login');
        }

        return view(
            'auth.social.confirmOldPassword',
            [
                'token'    => $this->request->session()->get('access_token'),
                'provider' => $provider,
                'user'     => $this->request->session()->get('user')
            ]
        );
    }

    /**
     * Submit action for the password confirmation form.
     * Makes sure existing user's password matches the entered one
     *
     * @return RedirectResponse to the home page if the password is correct, otherwise back to the confirmation page
     *
     * @throws InvalidArgumentException if social provider for which the password is confirmed is not supported
     */
    public function confirmPasswordPost()
    {
        try {
            $user = User::findOrFail($this->request->user);
            $socialToken = $this->request->session()->get('access_token');
            $password = $this->request->password;
            $provider = $this->request->session()->get('access_provider');
            $socialUserData = Socialite::driver($provider)->userFromToken($socialToken);

            if (Auth::attempt(['id' => $user->id, 'password' => $password])) {
                $user->setAttribute(User::getUserIdFieldForSocialProvider($provider), $socialUserData->id);
                $this->loginUserWithSocial($user, $provider, $socialToken);
                return redirect('/');
            } else {
                return redirect(route('social.login.confirmPassword', ['provider' => $provider]))
                    ->with('user', $user)
                    ->withInput()
                    ->withErrors(['password' => 'Incorrect password']);
            }
        } catch (ModelNotFoundException $e) {
            return redirect('/login');
        }
    }

    /**
     * Logs user in based on social provider and its access token
     *
     * @param User   $user to be logged in
     * @param string $provider string representation of the social login provider {@see SUPPORTED_PROVIDERS}
     * @param string $accessToken from the provider
     *
     * @throws InvalidArgumentException if social login provider is not supported
     */
    protected function loginUserWithSocial(User $user, string $provider, string $accessToken): void
    {
        $user->setAttribute(User::getAccessTokenFieldForSocialProvider($provider), $accessToken);
        $user->save();
        Auth::login($user);
        $accessToken = $user->createToken($user->email)->accessToken;

        $userDetails = User::getDetailsByUser($user);
        $response = ['user' => $userDetails, 'access_token' => $accessToken];
        Session::put('groupkit_auth', base64_encode(json_encode($response)));
    }
}
