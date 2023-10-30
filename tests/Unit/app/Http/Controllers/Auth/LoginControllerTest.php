<?php

namespace Tests\Unit\app\Http\Controllers\Auth;

use App\Http\Middleware\TeamUser;
use App\User;
use App\PasswordResets;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

/**
 * Class LoginControllerTest adds test coverage for {@see \App\Http\Controllers\Auth\LoginController} class
 *
 * @package Tests\Unit\app\Http\Controllers\Auth
 * @coversDefaultClass \App\Http\Controllers\Auth\LoginController
 */
class LoginControllerTest extends TestCase
{
    use DatabaseTransactions;
    use DatabaseMigrations;

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
     * that init protects methods from guest users
     *
     * @covers ::init
     *
     * @dataProvider init_withVariousRoutesProvider
     *
     * @param string $requestType of the tested route
     * @param string $url of the tested route
     * @param array $requestData of request param
     * @param int $expectedCode of the tested method call
     */
    public function init_withVariousRoutes_returnStatusCode(
        string $requestType,
        string $url,
        array $requestData,
        int $expectedCode
    ) {
        User::factory(['email' => 'john_doe@gmail.com'])->create();

        $response = $this->json($requestType, $url, $requestData);

        $response->assertStatus($expectedCode);
    }

    /**
     * Data provider for {@see init_withVariousRoutes_returnStatusCode}
     *
     * @return array[] requestType, url, requestData, expectedCode
     */
    public function init_withVariousRoutesProvider(): array
    {
        return [
            [
                'requestType' => 'GET',
                'url' => 'appLogout/' . base64_encode(json_encode(['user' => []])),
                'requestData' => [],
                'expectedCode' => Response::HTTP_UNAUTHORIZED,
            ],
            [
                'requestType' => 'GET',
                'url' => 'auto_login/' . base64_encode(json_encode(['user' => []])),
                'requestData' => [],
                'expectedCode' => Response::HTTP_BAD_REQUEST,
            ],
            [
                'requestType' => 'POST',
                'url' => 'verify',
                'requestData' => [
                    'email' => 'test@gmail.com',
                ],
                'expectedCode' => Response::HTTP_OK,
            ],
            [
                'requestType' => 'GET',
                'url' => 'logout',
                'requestData' => [],
                'expectedCode' => Response::HTTP_NO_CONTENT,
            ],
        ];
    }

    /**
     * @test
     * that login authenticates the user and redirects to a home page if:
     * 1. the user has an account in the database
     * 2. the user account is activated
     * 3. the user password is set
     * 4. the user is successfully authenticated with email and password
     *
     * @covers ::login
     */
    public function login_ifUserCanAuthenticate_redirectsToHomePage()
    {
        $this->artisan('passport:install');
        $user = User::factory()->create(['status' => 1]);

        $stubbedAccessToken = '31282348f32h32an131hbj41hjb';
        $userMock = $this->mock(User::class);
        $userMock->shouldReceive('createToken')->andReturn((object)['accessToken' => $stubbedAccessToken]);
        $userMock->shouldReceive('subscriptionsPlan')->andReturn(null);
        $userMock->shouldReceive('activePlan')->andReturn(true);
        $userMock->shouldReceive('getDetailsByUser')->andReturn($user);

        $response = $this->post('login', ['email' => $user->email, 'password' => 'password']);

        $this->assertAuthenticated();
        $response->assertStatus(Response::HTTP_FOUND);
        $response->assertRedirect('/');
    }

    /**
     * @test
     * that login returns a response with validation error according to the provided params
     *
     * @covers ::login
     *
     * @dataProvider login_withInvalidCredentialsProvider
     *
     * @param array $requestData for creating user
     * @param int $expectedCode of the tested method call
     * @param string $expectedMessage of the tested method call
     */
    public function login_withInvalidCredentials_returnsValidationErrors(
        array $requestData,
        int $expectedCode,
        string $expectedMessage
    ) {
        $user = User::factory()->create($requestData);

        $response = $this->json('POST', 'login', ['email' => $user->email, 'password' => 'password']);

        $errors = session()->get('errors');

        $this->assertEquals($expectedMessage, $errors->get('email')[0]);
        $response->assertStatus($expectedCode);
    }

    /**
     * Data provider for {@see login_withInvalidCredentials_returnsValidationErrors}
     *
     * @return array[] containing requestData, expectedCode and expected validation error message
     */
    public function login_withInvalidCredentialsProvider(): array
    {
        return [
            'Incorrect Email' => [
                'requestData' => [
                    'email' => '',
                    'password' => 'password',
                    'status' => 1,
                ],
                'expectedCode' => Response::HTTP_FOUND,
                'expectedMessage' => 'Please try again! Those credentials do not match our records.',
            ],
            'Incorrect Password' => [
                'requestData' => [
                    'email' => 'email@gmail.com',
                    'password' => 'password123',
                    'status' => 1,
                ],
                'expectedCode' => Response::HTTP_FOUND,
                'expectedMessage' => 'Please try again! Those credentials do not match our records.',
            ],
            'Inactive Account' => [
                'requestData' => [
                    'email' => 'email@gmail.com',
                    'password' => 'password',
                    'status' => 0,
                ],
                'expectedCode' => Response::HTTP_FOUND,
                'expectedMessage' => 'Your account is not active.',
            ],
        ];
    }

    /**
     * @test
     * that login redirects user to setPasswordPage if user has not set the password.
     *
     * @covers ::login
     */
    public function login_whenUserHasNotPassword_redirectsSetPasswordPageView()
    {
        $user = User::factory()->create([
            'email' => 'email@gmail.com',
            'password' => '',
            'status' => 1,
        ]);

        $response = $this->post('login', ['email' => $user->email, 'password' => 'password']);

        $response->assertSee('setPassword');
        $response->assertStatus(Response::HTTP_FOUND);
    }

    /**
     * @test
     * that appLogout removes authentication token from the session
     *
     * @covers ::appLogout
     */
    public function appLogout_withProvidedValidToken_returnsSuccessResponse()
    {
        $this->withoutMiddleware(TeamUser::class);
        $user = $this->actingAsUser();
        session()->put('groupkit_auth', 'stubbed_authentication');

        $token = base64_encode(json_encode(['user' => $user]));

        $response = $this->get(route('appLogout', $token));

        $response->assertOk();
        $response->assertSessionMissing('groupkit_auth');
    }

    /**
     * @test
     * that autoLogin returns success response if the valid token is passed, otherwise returns an error response
     *
     * @covers ::autoLogin
     *
     * @dataProvider autoLogin_withVariousTokenCodeProvider
     *
     * @param bool $isToken passed indicator
     * @param int $expectedCode of the tested method call
     * @param string $expectedMessage of the tested method call
     */
    public function autoLogin_withVariousTokenCode_returnsStatusCode(
        bool $isToken,
        int $expectedCode,
        string $expectedMessage
    ) {
        $this->artisan('passport:install');

        $user = User::factory()->create();
        $token = base64_encode(json_encode(['user' => $isToken ? $user : null]));

        $accessToken = '31282348f32h32an131hbj41hjb';

        $userMock = $this->mock(User::class);
        $userMock->shouldReceive('createToken')->andReturn((object)['accessToken' => $accessToken]);
        $userMock->shouldReceive('getDetailsByUser')->andReturn($user);

        $response = $this->json('GET', route('autoLogin', $token));

        $response->assertJsonFragment(['message' => $expectedMessage]);
        if ($isToken) {
            $response->assertSessionHas('groupkit_auth');
        }
        $response->assertStatus($expectedCode);
    }

    /**
     * Data provider for {@see autoLogin_withVariousTokenCode_returnsStatusCode}
     *
     * @return array[] containing is a token indicator, expected code, and expected message of the tested route call
     */
    public function autoLogin_withVariousTokenCodeProvider(): array
    {
        return [
            'Passed Valid Token' => [
                'isToken' => true,
                'expectedCode' => Response::HTTP_OK,
                'expectedMessage' => 'The user is logged in',
            ],
            'Passed Invalid Token' => [
                'isToken' => false,
                'expectedCode' => Response::HTTP_BAD_REQUEST,
                'expectedMessage' => 'The user is not logged in',
            ],
        ];
    }

    /**
     * @test
     * that setPassword always returns set password view
     *
     * @covers ::setPassword
     */
    public function setPassword_always_returnsSetPasswordView()
    {
        $email = 'test@gmail.com';
        $token = Str::random(40);

        $response = $this->get("setPassword/{$email}/{$token}");

        $response->assertSee($email);
        $response->assertViewIs('auth.passwords.setPass');
        $response->assertOk();
    }

    /**
     * @test
     * that verifyEmail returns validation error when request data is invalid, otherwise returns a success response
     *
     * @covers ::verifyEmail
     *
     * @dataProvider verifyEmail_withVariousRequestDataProvider
     *
     * @param array $requestData for creating custom user
     * @param int $expectedCode of the tested method call
     * @param string $expectedMessage of the tested method call
     */
    public function verifyEmail_withVariousRequestData_returnsResponse(
        array $requestData,
        int $expectedCode,
        string $expectedMessage
    ) {
        $user = User::factory()->create($requestData);
        $this->actingAs($user);

        $response = $this->json('POST', route('verify'), ['email' => $user->email]);

        $response->assertStatus($expectedCode);
        $response->assertJsonFragment(['message' => $expectedMessage]);
    }

    /**
     * Data provider for {@see verifyEmail_withVariousRequestData_returnsStatusCodeWithMessage}
     *
     * @return array[] containing request data, expected code and expected message of the tested method call
     */
    public function verifyEmail_withVariousRequestDataProvider(): array
    {
        return [
            'Incorrect Email' => [
                'requestData' => [
                    'email' => '',
                    'password' => 'password',
                    'status' => 1,
                ],
                'expectedCode' => Response::HTTP_OK,
                'expectedMessage' => 'Please try again! Those credentials do not match our records.',
            ],
            'Incorrect Email With Inactive Status' => [
                'requestData' => [
                    'email' => 'email@gmail.com',
                    'password' => 'password',
                    'status' => 0,
                ],
                'expectedCode' => Response::HTTP_OK,
                'expectedMessage' => 'Your account is not active.',
            ],
            'Correct Email and Password is Empty' => [
                'requestData' => [
                    'email' => 'email@gmail.com',
                    'password' => '',
                    'status' => 1,
                ],
                'expectedCode' => Response::HTTP_OK,
                'expectedMessage' => 'Redirect',
            ],
            'Correct Email' => [
                'requestData' => [
                    'email' => 'email@gmail.com',
                    'password' => 'password',
                    'status' => 1,
                ],
                'expectedCode' => Response::HTTP_OK,
                'expectedMessage' => 'Success',
            ],
        ];
    }

    /**
     * @test
     * that updatePassword returns validation error when request data are not valid
     *
     * @dataProvider updatePassword_withInvalidCredentialsProvider
     *
     * @covers ::updatePassword
     * @param array $requestData for creating custom user
     * @param int $expectedCode of the tested method call
     * @param string $expectedMessage of the tested method call
     */
    public function updatePassword_withInvalidCredentials_returnsStatusCode(
        array $requestData,
        int $expectedCode,
        string $expectedMessage
    ) {
        User::factory()->create([
            'email' => 'email@ggmail.com',
            'password' => null,
        ]);
        PasswordResets::factory()->create([
            'email' => $requestData['email'],
            'token' => Hash::make($requestData['token']),
        ]);

        $response = $this->json('POST', 'setPassword', $requestData);
        $errorMessage = $response['errors']['password'][0]
            ?? $response['errors']['email'][0]
            ?? $response['errors']['token'][0];

        $this->assertEquals($expectedMessage, $errorMessage);
        $response->assertStatus($expectedCode);
    }

    /**
     * Data provider for {@see updatePassword_withInvalidCredentials_returnsStatusCode}
     *
     * @return array[] requestData, expectedCode, expectedMessage
     */
    public function updatePassword_withInvalidCredentialsProvider(): array
    {
        $token = Str::random(40);

        return [
            'Empty Password' => [
                'requestData' => [
                    'email' => 'email@ggmail.com',
                    'password' => '',
                    'password_confirmation' => '',
                    'token' => $token,
                ],
                'expectedCode' => Response::HTTP_UNPROCESSABLE_ENTITY,
                'expectedMessage' => 'The password field is required.',
            ],
            'Password And Confirmed Not Match' => [
                'requestData' => [
                    'email' => 'email@ggmail.com',
                    'password' => '12345678',
                    'password_confirmation' => 'password',
                    'token' => $token,
                ],
                'expectedCode' => Response::HTTP_UNPROCESSABLE_ENTITY,
                'expectedMessage' => 'The password confirmation does not match.',
            ],
            'The password field required minimum 8 characters' => [
                'requestData' => [
                    'email' => 'email@ggmail.com',
                    'password' => '12345',
                    'password_confirmation' => 'password',
                    'token' => $token,
                ],
                'expectedCode' => Response::HTTP_UNPROCESSABLE_ENTITY,
                'expectedMessage' => 'The password must be at least 8 characters.',
            ],
            'Email address not found' => [
                'requestData' => [
                    'email' => '',
                    'password' => 'Password123',
                    'password_confirmation' => 'Password123',
                    'token' => $token,
                ],
                'expectedCode' => Response::HTTP_UNPROCESSABLE_ENTITY,
                'expectedMessage' => 'The email field is required.',
            ],
            'Email address not valid' => [
                'requestData' => [
                    'email' => 'email-ggmail.com',
                    'password' => 'Password123',
                    'password_confirmation' => 'Password123',
                    'token' => $token,
                ],
                'expectedCode' => Response::HTTP_UNPROCESSABLE_ENTITY,
                'expectedMessage' => 'The email must be a valid email address.',
            ],
            'Email address does not exist in the database' => [
                'requestData' => [
                    'email' => 'email123@ggmail.com',
                    'password' => 'Password123',
                    'password_confirmation' => 'Password123',
                    'token' => $token,
                ],
                'expectedCode' => Response::HTTP_UNPROCESSABLE_ENTITY,
                'expectedMessage' => 'The selected email is invalid.',
            ],
            'Empty token' => [
                'requestData' => [
                    'email' => 'email@ggmail.com',
                    'password' => 'Password123',
                    'password_confirmation' => 'Password123',
                    'token' => '',
                ],
                'expectedCode' => Response::HTTP_UNPROCESSABLE_ENTITY,
                'expectedMessage' => 'The token field is required.',
            ],
        ];
    }

    /**
     * @test
     * that updatePassword returns validation error when request data are not valid
     *
     * @dataProvider updatePassword_withInvalidCredentialsAndPasswordResetsRequirementsProvider
     *
     * @covers ::updatePassword
     *
     * @param array $requestData for creating request params
     * @param array $createData for preparing user and password reset for test case
     * @param int $expectedCode of the tested method call
     * @param string $expectedMessage of the session error in response
     */
    public function updatePassword_withInvalidCredentials_returnsPasswordResetsMessageAndStatusCode(
        array $requestData,
        array $createData,
        int $expectedCode,
        string $expectedMessage
    ) {
        User::factory()->create([
            'email' => $createData['email'],
            'password' => $createData['password'],
        ]);

        PasswordResets::factory()->create([
            'email' => $requestData['email'],
            'token' => Hash::make($createData['token']),
            'created_at' => $createData['createdAt'],
        ]);

        $response = $this->json('POST', 'setPassword', $requestData);

        $errors = session()->get('errors');
        $errorMessage = $errors->get('email')[0] ?? $errors->get('token')[0];

        $this->assertEquals($expectedMessage, $errorMessage);
        $response->assertStatus($expectedCode);
    }

    /**
     * Data provider for {@see updatePassword_withInvalidCredentials_returnsPasswordResetsMessageAndStatusCode}
     *
     * @return array[] requestData, expectedCode, expectedMessage
     */
    public function updatePassword_withInvalidCredentialsAndPasswordResetsRequirementsProvider(): array
    {
        $token = Str::random(40);

        return [
            'The password is already set' => [
                'requestData' => [
                    'email' => 'email@ggmail.com',
                    'password' => 'Password123',
                    'password_confirmation' => 'Password123',
                    'token' => $token,
                ],
                'createData' => [
                    'email' => 'email@ggmail.com',
                    'password' => 'Password1234',
                    'token' => $token,
                    'createdAt' => Carbon::now(),
                ],
                'expectedCode' => Response::HTTP_FOUND,
                'expectedMessage' => 'The password is already set',
            ],
            'Invalid token' => [
                'requestData' => [
                    'email' => 'email@ggmail.com',
                    'password' => 'Password123',
                    'password_confirmation' => 'Password123',
                    'token' => $token,
                ],
                'createData' => [
                    'email' => 'email@ggmail.com',
                    'password' => null,
                    'token' => Str::random(40),
                    'createdAt' => Carbon::now(),
                ],
                'expectedCode' => Response::HTTP_FOUND,
                'expectedMessage' => 'This password reset token is invalid.',
            ],
            'Expired token' => [
                'requestData' => [
                    'email' => 'email@ggmail.com',
                    'password' => 'Password123',
                    'password_confirmation' => 'Password123',
                    'token' => $token,
                ],
                'createData' => [
                    'email' => 'email@ggmail.com',
                    'password' => null,
                    'token' => $token,
                    'createdAt' => Carbon::now()->subYears(5),
                ],
                'expectedCode' => Response::HTTP_FOUND,
                'expectedMessage' => 'This password reset token is invalid.',
            ],
        ];
    }

    /**
     * @test
     * that updatePassword returns success response if valid user data is provided
     *
     * @covers ::updatePassword
     */
    public function updatePassword_withValidProvideData_returnSuccessResponse()
    {
        $this->artisan('passport:install');
        $user = User::factory()->create([
            'email' => 'email@gmail.com',
            'password' => null,
            'status' => 1,
        ]);

        $token = Str::random(40);
        PasswordResets::factory()->create([
            'email' => $user->email,
            'token' => Hash::make($token),
            'created_at' => Carbon::now(),
        ]);

        //custom request data
        $requestData['email'] = $user->email;
        $requestData['token'] = $token;
        $requestData['password'] = 'password';
        $requestData['password_confirmation'] = 'password';

        $stubbedAccessToken = '31282348f32h32an131hbj41hjb';
        $userMock = $this->mock(User::class);
        $userMock->shouldReceive('createToken')->andReturn((object)['accessToken' => $stubbedAccessToken]);
        $userMock->shouldReceive('subscriptionsPlan')->andReturn(null);
        $userMock->shouldReceive('activePlan')->andReturn(true);
        $userMock->shouldReceive('getDetailsByUser')->andReturn($user);

        $response = $this->post('setPassword', $requestData);

        $response->assertSessionHas('groupkit_auth');
        $response->assertSee('home');
        $response->assertStatus($response->getStatusCode());
    }
}
