<?php

namespace Tests\Unit\app\Http\Controllers\Auth;

use App\Http\Controllers\Auth\SocialController;
use App\Plan;
use App\User;
use Exception;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Laravel\Socialite\Facades\Socialite;
use Mockery;
use ReflectionException;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;
use Tests\TestHelper;

/**
 * Class SocialControllerTest adds test coverage for {@see \App\Http\Controllers\Auth\SocialController} class
 *
 * @package Tests\Unit\app\Http\Auth
 * @coversDefaultClass \App\Http\Controllers\Auth\SocialController
 */
class SocialControllerTest extends TestCase
{
    use DatabaseTransactions;
    use DatabaseMigrations;

    /**
     * @var array stubbed User data for Social login
     */
    private const SOCIALITE_USER_DATA = [
        'id'       => 1234567890,
        'email'    => 'user@test.com',
        'nickName' => 'Pseudo',
        'name'     => 'Arlette Laguiller',
        'avatar'   => 'https://en.gravatar.com/userimage',
    ];

    /**
     * @var string supported provider for login with Socialite {@see Socialite}
     */
    private string $provider = SocialController::SUPPORTED_PROVIDERS[0];

    /**
     * Setup test dependencies
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->assertGuest();
    }

    /**
     * @test
     * that isProviderSupported returns true if provided provider is supported, otherwise false
     *
     * @covers ::isProviderSupported
     *
     * @dataProvider isProviderSupported_withVariousSocialProvidersProvider
     *
     * @param string $provider represents third party network {@see https://laravel.com/docs/8.x/socialite#introduction}
     *                         to authenticate user via account in that network
     * @param bool $expectedResult of the tested method call
     */
    public function isProviderSupported_withVariousSocialProviders_returnsBoolValue(
        string $provider,
        bool $expectedResult
    ) {
        $this->assertEquals($expectedResult, SocialController::isProviderSupported($provider));
    }

    /**
     * Data provider for {@see isProviderSupported_withVariousSocialProviders_returnsBoolValue}
     *
     * @return array[] containing provider and expected result of the tested method call
     */
    public function isProviderSupported_withVariousSocialProvidersProvider(): array
    {
        return [
            ['provider' => 'facebook', 'expectedResult' => true],
            ['provider' => 'github', 'expectedResult' => false],
            ['provider' => 'linkedin', 'expectedResult' => false],
        ];
    }

    /**
     * @test
     * that redirectToProvider redirects to the route according to the provided social provider
     *
     * @covers ::redirectToProvider
     *
     * @dataProvider redirectToProvider_withVariousProvidersProvider
     *
     * @param string $provider represents third party network {@see https://laravel.com/docs/8.x/socialite#introduction}
     *                         to authenticate user via account in that network
     * @param string $redirectTo url of the provider if is supported, otherwise home route
     */
    public function redirectToProvider_withVariousProviders_returnsRedirection(
        string $provider,
        string $redirectTo
    ) {
        $response = $this->get(route('social.login', $provider));

        $this->assertGuest();
        $response->assertStatus(Response::HTTP_FOUND);
        $response->assertRedirect($redirectTo);
    }

    /**
     * Data provider for {@see redirectToProvider_withVariousProviders_returnsRedirection}
     *
     * @return array[] containing social provider and URL redirect
     */
    public function redirectToProvider_withVariousProvidersProvider(): array
    {
        return [
            [
                'provider'   => 'facebook',
                'redirectTo' => 'https://www.facebook.com/v3.3/dialog/oauth?redirect_uri=http%3A%2F%2Flocalhost' .
                                '%2Flogin%2Ffacebook%2Fcallback&scope=email&response_type=code&display=page',
            ],
            [
                'provider'   => 'linkedin',
                'redirectTo' => '/',
            ],
        ];
    }

    /**
     * @test
     * that handleProviderCallback returns redirection to the previous page for unsupported Socialite providers
     *
     * @covers ::handleProviderCallback
     *
     * @dataProvider handleProviderCallback_withUnsupportedProvidersProvider
     *
     * @param string $provider represents third party network {@see https://laravel.com/docs/8.x/socialite#introduction}
     *                         that provides user authentication
     */
    public function handleProviderCallback_withUnsupportedProviders_returnsRedirect(string $provider)
    {
        $response = $this->get("login/{$provider}/callback");

        $this->assertGuest();
        $response->assertStatus(Response::HTTP_FOUND);
        $response->assertRedirect('/');
    }

    /**
     * Data provider for {@see handleProviderCallback_withUnsupportedProviders_returnsRedirect}
     *
     * @return array[] containing unsupported social providers
     */
    public function handleProviderCallback_withUnsupportedProvidersProvider(): array
    {
        return [
            ['provider' => 'linkedin'],
            ['provider' => 'github'],
            ['provider' => 'google'],
        ];
    }

    /**
     * @test
     * that handleProviderCallback redirects to the plans page if:
     * 1. the supplied provider is supported
     * 2. user doesn't already exist
     * 3. a subscription plan id is not provided in the request
     *
     * @covers ::handleProviderCallback
     *
     * @throws ReflectionException from {@see handleProviderCallbackSetUp} method
     */
    public function handleProviderCallback_ifUserDoesntHaveAccountInDatabase_returnsToPlansPage()
    {
        list($provider, $providerMock, $socialiteUser, $token) = $this->handleProviderCallbackSetUp();
        $providerMock->shouldReceive('stateless')->andReturnSelf();
        $providerMock->shouldReceive('user')->andReturn($socialiteUser);

        Socialite::shouldReceive('driver')->with($provider)->andReturn($providerMock);

        $response = $this->get("login/{$provider}/callback");

        $response->assertStatus(Response::HTTP_FOUND);
        $response->assertRedirect(route('plans.index'));
        $this->assertGuest();
        $response->assertSessionHas('access_token', $token);
        $response->assertSessionHas('access_provider', $provider);
        $response->assertSessionHas(
            'first_name',
            substr(self::SOCIALITE_USER_DATA['name'], 0, strpos(self::SOCIALITE_USER_DATA['name'], ' '))
        );
        $response->assertSessionHas(
            'last_name',
            substr(self::SOCIALITE_USER_DATA['name'], strpos(self::SOCIALITE_USER_DATA['name'], ' ') + 1)
        );
        $response->assertSessionHas('email', self::SOCIALITE_USER_DATA['email']);
    }

    /**
     * @test
     * that handleProviderCallback redirects to the specific plan page if:
     * 1. the supplied provider is supported
     * 2. user doesn't already exist
     * 3. a subscription plan id is provided in the request
     *
     * @covers ::handleProviderCallback
     *
     * @dataProvider handleProviderCallback_withVariousPlanIdsProvider
     *
     * @param string $planId represent id of the stripe plan {@see Plan::STRIPE_PLAN_IDS}
     *
     * @throws ReflectionException from {@see handleProviderCallbackSetUp} method
     */
    public function handleProviderCallback_withVariousPlanIds_returnsRedirectToPlan(string $planId)
    {
        list($provider, $providerMock, $socialiteUser, $token) = $this->handleProviderCallbackSetUp();
        $providerMock->shouldReceive('stateless')->andReturnSelf();
        $providerMock->shouldReceive('user')->andReturn($socialiteUser);

        Socialite::shouldReceive('driver')->with($provider)->andReturn($providerMock);

        $response = $this->get("login/{$provider}/callback?" . http_build_query(['state' => $planId]));

        $response->assertStatus(Response::HTTP_FOUND);
        $response->assertRedirect(route('plans.show', $planId));
        $this->assertGuest();
        $response->assertSessionHas('access_token', $token);
        $response->assertSessionHas('access_provider', $provider);
        $response->assertSessionHas(
            'first_name',
            substr(self::SOCIALITE_USER_DATA['name'], 0, strpos(self::SOCIALITE_USER_DATA['name'], ' '))
        );
        $response->assertSessionHas(
            'last_name',
            explode(' ', self::SOCIALITE_USER_DATA['name'])[1]
        );
        $response->assertSessionHas('email', self::SOCIALITE_USER_DATA['email']);
    }

    /**
     * Data provider for {@see handleProviderCallback_withVariousPlanIds_returnsRedirectToPlan}
     *
     * @return array[] containing plan id
     */
    public function handleProviderCallback_withVariousPlanIdsProvider(): array
    {
        return [
            ['planId' => Plan::STRIPE_PLAN_IDS['default']['BASIC']],
            ['planId' => Plan::STRIPE_PLAN_IDS['default']['PRO_MONTHLY']],
            ['planId' => Plan::STRIPE_PLAN_IDS['default']['PRO_ANNUAL']],
        ];
    }

    /**
     * @test
     * that handleProviderCallback redirects to the confirm password page if:
     * 1. the supplied social provider is supported
     * 2. the user already has an account in the database
     * 3. user didn't authenticate via the supplied social provider before
     *
     * @covers ::handleProviderCallback
     *
     * @throws ReflectionException from {@see handleProviderCallbackSetUp} method
     */
    public function handleProviderCallback_whenUserHasAccountInDatabase_returnsToConfirmPasswordView()
    {
        User::factory()->create(['email' => self::SOCIALITE_USER_DATA['email']]);
        list($provider, $providerMock, $socialiteUser, $token) = $this->handleProviderCallbackSetUp();
        $providerMock->shouldReceive('stateless')->andReturnSelf();
        $providerMock->shouldReceive('user')->andReturn($socialiteUser);

        Socialite::shouldReceive('driver')->with($provider)->andReturn($providerMock);

        $response = $this->get("login/{$provider}/callback");

        $response->assertSessionHas('access_token', $token);
        $response->assertStatus(Response::HTTP_FOUND);
        $response->assertRedirect(route('social.login.confirmPassword', $provider));
        $this->assertGuest();
    }

    /**
     * @test
     * that handleProviderCallback redirects to the login page with an error message
     * if an exception occurs during the social authentication callback handling
     *
     * @covers ::handleProviderCallback
     *
     * @throws ReflectionException from {@see handleProviderCallbackSetUp} method
     */
    public function handleProviderCallback_whenExceptionIsThrown_returnsToLogin()
    {
        list($provider, $providerMock) = $this->handleProviderCallbackSetUp();

        $providerMock->shouldReceive('stateless')->andReturnSelf();
        $providerMock->shouldReceive('user')->andThrow(new Exception());

        Socialite::shouldReceive('driver')->with($provider)->andReturn($providerMock);

        $response = $this->get("login/{$provider}/callback");

        $response->assertStatus(Response::HTTP_FOUND);
        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors(["{$this->provider}_login"], 'Social Login failed');
        $this->assertGuest();
    }

    /**
     * @test
     * that handleProviderCallback redirects to the home page if:
     * 1. the supplied social provider is supported
     * 2. the user has an account in the database
     * 3. user did authenticate via the supplied social provider before
     * 4. user is successfully authenticated via the social provider
     *
     * @covers ::handleProviderCallback
     *
     * @throws ReflectionException from {@see handleProviderCallbackSetUp} method
     */
    public function handleProviderCallback_whenUserHasAccountWithSocialLoginInDatabase_returnsAuthenticatedUser()
    {
        $this->artisan('passport:install');
        list($provider, $driverMock, $socialiteUser, $token) = $this->handleProviderCallbackSetUp();

        $user = User::factory()->create([
            User::getUserIdFieldForSocialProvider($provider) => self::SOCIALITE_USER_DATA['id']
        ]);

        $driverMock->shouldReceive('stateless')->andReturnSelf();
        $driverMock->shouldReceive('user')->andReturn($socialiteUser);

        Socialite::shouldReceive('driver')->with($provider)->andReturn($driverMock);

        $userMock = $this->getMockBuilder(User::class)
            ->setProxyTarget($user)
            ->disableOriginalConstructor()
            ->onlyMethods(['createToken'])
            ->getMock();

        $this->app->instance('User', $userMock);

        $this->partialMock(SocialController::class)
            ->shouldAllowMockingProtectedMethods()
            ->shouldReceive('loginUserWithSocial')
            ->withArgs(
                function ($argumentUser, $argumentProvider, $argumentAccessToken) use ($token, $provider, $user) {
                    /** @var User $argumentUser */
                    static::assertInstanceOf(User::class, $argumentUser);
                    static::assertEquals($user->id, $argumentUser->id);
                    static::assertEquals($provider, $argumentProvider);
                    static::assertEquals($token, $argumentAccessToken);
                    return true;
                }
            )
            ->once()
            ->andReturnNull();

        $response = $this->get("login/{$provider}/callback");
        $response->assertStatus(Response::HTTP_FOUND);
        $response->assertRedirect('/');
        $this->assertGuest();
    }

    /**
     * Setup method for handleProviderCallback test cases
     *
     * @throws ReflectionException if properties id, token, email or name doesn't exists
     */
    public function handleProviderCallbackSetUp(): array
    {
        $token = 're3323fhsdnkjd1312';
        $socialiteUser = Mockery::mock('Laravel\Socialite\Two\User');
        $socialiteUser->shouldReceive('getId')->andReturn(self::SOCIALITE_USER_DATA['id']);
        $socialiteUser->shouldReceive('getEmail')->andReturn(self::SOCIALITE_USER_DATA['email']);
        $socialiteUser->shouldReceive('getNickname')->andReturn(self::SOCIALITE_USER_DATA['nickName']);
        $socialiteUser->shouldReceive('getName')->andReturn(self::SOCIALITE_USER_DATA['name']);
        $socialiteUser->shouldReceive('getAvatar')->andReturn(self::SOCIALITE_USER_DATA['avatar']);
        TestHelper::setNonPublicProperty($socialiteUser, 'id', self::SOCIALITE_USER_DATA['id']);
        TestHelper::setNonPublicProperty($socialiteUser, 'token', $token);
        TestHelper::setNonPublicProperty($socialiteUser, 'email', self::SOCIALITE_USER_DATA['email']);
        TestHelper::setNonPublicProperty($socialiteUser, 'name', self::SOCIALITE_USER_DATA['name']);

        $providerMock = Mockery::mock('Laravel\Socialite\Contracts\Provider');

        return [$this->provider, $providerMock, $socialiteUser, $token];
    }

    /**
     * @test
     * that showPasswordConfirmation returns to the login page if one of the following condition is true:
     * 1. provider is empty string
     * 2. session doesn't have access_provider
     * 3. session doesn't have access_token
     * 3. session doesn't have user
     *
     * @covers ::showPasswordConfirmation
     *
     * @dataProvider showPasswordConfirmation_withVariousSessionItemsProvider
     *
     * @param string $provider represents third party network {@see https://laravel.com/docs/8.x/socialite#introduction}
     *                         to authenticate user via account in that network
     * @param array $sessionItems with access_token, access_provider and user values
     */
    public function showPasswordConfirmation_withVariousSessionItems_returnsRedirectToLogin(
        string $provider,
        array $sessionItems
    ) {
        foreach ($sessionItems as $sessionKey => $sessionValue) {
            if ($sessionKey === 'user' && $sessionValue) {
                $sessionValue = User::factory()->create();
            }
            session()->put($sessionKey, $sessionValue);
        }
        $response = $this->get(route('social.login.confirmPassword', $provider));

        $this->assertGuest();
        $response->assertStatus(Response::HTTP_FOUND);
        $response->assertRedirect(route('login'));
        foreach ($sessionItems as $sessionKey => $sessionValue) {
            $sessionKey !== 'user' ? $response->assertSessionHas($sessionKey, $sessionValue) : null;
        }
    }


    /**
     * Data provider for {@see showPasswordConfirmation_withVariousSessionItems_returnsRedirectToLogin}
     *
     * @return array[] containing provider and session items
     */
    public function showPasswordConfirmation_withVariousSessionItemsProvider(): array
    {
        return [
            'Authorization fails when only provider is included' => [
                'provider' => $this->provider,
                'sessionItems' => [],
            ],
            'Authorization fails when provider and access_provider are included' => [
                'provider' => $this->provider,
                'sessionItems' => [
                    'access_provider' => $this->provider,
                ],
            ],
            'Authorization fails when provider, access_provider and access_token are included' => [
                'provider' => $this->provider,
                'sessionItems' => [
                    'access_provider' => $this->provider,
                    'access_token' => 'dasdd1312brkhb2h1bh23krb1e',
                ],
            ],
            'Authorization fails when provider, access_provider and user are included' => [
                'provider' => $this->provider,
                'sessionItems' => [
                    'access_provider' => $this->provider,
                    'user' => true,
                ],
            ],
        ];
    }

    /**
     * @test
     * that showPasswordConfirmation displays the password confirmation form if:
     * 1. the social provider is supplied either as a request or session parameter
     * 2. session has an access_token
     * 3. session has the user
     *
     * @covers ::showPasswordConfirmation
     */
    public function showPasswordConfirmation_whenAuthorizationPassed_returnsConfirmOldPasswordView()
    {
        session()->put('access_provider', $this->provider);
        $accessToken = 'dasdsa21312fscae123413';
        session()->put('access_token', $accessToken);
        $user = User::factory()->create();
        session()->put('user', $user);

        $response = $this->get(route('social.login.confirmPassword', $this->provider));

        $this->assertGuest();
        $response->assertViewIs('auth.social.confirmOldPassword');
        $response->assertStatus(Response::HTTP_OK);
        $response->assertViewHas(['provider' => $this->provider]);
        $response->assertViewHas(['token' => $accessToken]);
        $response->assertViewHas(['user' => $user]);
    }

    /**
     * @test
     * that confirmPasswordPost redirects to the login page if we can't find the user in the database
     *
     * @covers ::confirmPasswordPost
     */
    public function confirmPasswordPost_whenUserIsNotInDatabase_returnsRedirectToLogin()
    {
        $user = User::factory()->make();

        $response = $this->post(route('social.login.confirmPasswordPost'), ['user' => $user]);

        $this->assertGuest();
        $response->assertStatus(Response::HTTP_FOUND);
        $response->assertRedirect(route('login'));
    }

    /**
     * @test
     * that confirmPasswordPost redirects to the password confirmation page if the user provided an incorrect password
     *
     * @covers ::confirmPasswordPost
     */
    public function confirmPasswordPost_withIncorrectPassword_returnsErrorResponse()
    {
        $user = User::factory()->create();
        $accessToken = 'das312321as12312dq12312';
        session()->put('access_token', $accessToken);
        session()->put('access_provider', $this->provider);

        $providerMock = Mockery::mock('Laravel\Socialite\Contracts\Provider');
        $providerMock->shouldReceive('userFromToken')->andReturn(self::SOCIALITE_USER_DATA);
        Socialite::shouldReceive('driver')->with($this->provider)->andReturn($providerMock);

        $response = $this->post(route('social.login.confirmPasswordPost'), [
            'user' => $user->id,
            'password' => 'Password123',
        ]);

        $this->assertGuest();
        $response->assertStatus(Response::HTTP_FOUND);
        $response->assertRedirect(route('social.login.confirmPassword', ['provider' => $this->provider]));
        $response->assertSessionHasErrors(['password'], 'Incorrect password');
        $response->assertSessionHas('user');
    }

    /**
     * @test
     * that confirmPasswordPost authenticates and redirects the user to the home page
     * after a successful password confirmation
     *
     * @covers ::confirmPasswordPost
     *
     * @throws ReflectionException if property request doesn't exists
     */
    public function confirmPasswordPost_withValidLoginData_authenticatesTheUser()
    {
        $user = User::factory()->create();
        $userPassword = 'password';
        $accessToken = 'das312321as12312dq12312';
        $provider = $this->provider;
        session()->put('access_token', $accessToken);
        session()->put('access_provider', $provider);

        $requestMock = $this->createMock(Request::class);
        $requestMock->expects(static::exactly(2))->method('__get')
            ->withConsecutive(['user'], ['password'])
            ->willReturnOnConsecutiveCalls($user->id, $userPassword);
        $requestMock->expects(static::exactly(2))->method('session')->willReturnSelf();
        $requestMock->expects(static::exactly(2))->method('get')
            ->withConsecutive(['access_token'], ['access_provider'])
            ->willReturn($accessToken, $provider);

        $providerMock = Mockery::mock('Laravel\Socialite\Contracts\Provider');
        $providerMock->shouldReceive('userFromToken')->andReturn((object)self::SOCIALITE_USER_DATA);
        Socialite::shouldReceive('driver')->with($provider)->andReturn($providerMock);

        $socialControllerMock = $this->partialMock(SocialController::class);
        $socialControllerMock->shouldAllowMockingProtectedMethods()
            ->shouldReceive('loginUserWithSocial')
            ->withArgs(
                function ($argumentUser, $argumentProvider, $argumentAccessToken) use ($accessToken, $provider, $user) {
                    /** @var User $argumentUser */
                    static::assertInstanceOf(User::class, $argumentUser);
                    static::assertEquals($user->id, $argumentUser->id);
                    static::assertEquals($provider, $argumentProvider);
                    static::assertEquals($accessToken, $argumentAccessToken);
                    return true;
                }
            )
            ->once()
            ->andReturnNull();

        TestHelper::setNonPublicProperty($socialControllerMock, 'request', $requestMock);

        $response = $this->post(route('social.login.confirmPasswordPost'), [
            'user' => $user->id,
            'password' => $userPassword,
        ]);

        $this->assertAuthenticated();
        $response->assertStatus(Response::HTTP_FOUND);
        $response->assertRedirect('/');
    }

    /**
     * @test
     * that loginUserWithSocial logs user in with social provider
     *
     * @covers ::loginUserWithSocial
     *
     * @throws ReflectionException if loginUserWithSocial method is not defined
     */
    public function loginUserWithSocial_always_setsUserData()
    {
        $currentMock = $this->getMockBuilder(SocialController::class)
            ->disableOriginalConstructor()
            ->getMock();
        $userMock = $this->getMockBuilder(User::class)
            ->onlyMethods(['subscriptionsPlan', '__get', 'setAttribute', 'save', 'createToken'])
            ->disableOriginalConstructor()
            ->getMock();

        $userEmail = 'john.doe@gmail.com';
        $userId = 1;
        $stripeId = '';
        $userMock->method('__get')
            ->willReturnMap(
                [
                    ['id', $userId],
                    ['email', $userEmail],
                    ['stripe_id', $stripeId],
                ]
            );

        $accessToken = '31238717381ln32fs0123uf4sd4jh2';
        $userMock->expects(static::exactly(3))
            ->method('setAttribute')
            ->withConsecutive(
                [User::getAccessTokenFieldForSocialProvider($this->provider), $accessToken],
                ['plan_name', 'N/A'],
                ['access_team', false]
            )
            ->willReturnSelf();
        $userMock->expects(static::once())->method('save')->willReturnSelf();
        $stubbedAccessToken = '31282348f32h32an131hbj41hjb';

        $userMock->expects(static::once())
            ->method('createToken')
            ->with($userEmail)
            ->willReturn((object)['accessToken' => $stubbedAccessToken]);

        TestHelper::callNonPublicFunction(
            $currentMock,
            'loginUserWithSocial',
            [$userMock, $this->provider, $accessToken]
        );
    }
}
