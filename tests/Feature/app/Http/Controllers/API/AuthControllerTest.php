<?php

namespace Tests\Feature\app\Http\Controllers\API;

use App\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class AuthControllerTest
 *
 * @package Tests\Feature\app\Http\Controllers\API
 * @coversDefaultClass \App\Http\Controllers\API\AuthController
 */
class AuthControllerTest extends TestCase
{
    use DatabaseMigrations;
    use DatabaseTransactions;

    /**
     * Setup test dependencies
     *
     * @return void
     */
    public function setUp(): void
    {
        parent::setUp();
        $this->artisan('passport:install');
        $this->assertGuest();
    }

    /**
     * @test
     * that login returns a success response when the user authenticates
     *
     * @covers ::login
     */
    public function login_ifUserCanAuthenticate_returnsSuccessResponse()
    {
        $user = User::factory()->create();

        $response = $this->json('POST', 'api/login',
            [
                'email' => $user->email,
                'password' => 'password',
            ]
        );

        $this->assertAuthenticated();
        $this->assertEquals('success', $response['message']);
        $this->assertEquals($user->email, $response['data']['user']['email']);
    }

    /**
     * @test
     * that login returns validation error when email or password are not valid
     *
     * @covers ::login
     *
     * @dataProvider login_withInvalidCredentialsProvider
     *
     * @param string|null $email of the user {@see User}
     * @param string|null $password of the user {@see User}
     * @param int $expectedCode in response to an invalid request
     * @param string $expectedErrorMessage describing the validation errors
     */
    public function login_withInvalidCredentials_returnsValidationErrors(
        ?string $email,
        ?string $password,
        int $expectedCode,
        string $expectedErrorMessage
    ) {
        $this->createUserSetup();

        $response = $this->json('POST', 'api/login',
            [
                'email' => $email,
                'password' => $password,
            ]
        );

        $this->assertGuest(); # Verify that the login has failed
        $this->assertEquals($expectedCode, $response->getStatusCode());
        $this->assertEquals($expectedErrorMessage, $response['message']);
    }

    /**
     * Data provider for {@see login_withInvalidCredentials_returnsValidationErrors}
     *
     * @return array[] containing email, password and expected validation error message
     */
    public function login_withInvalidCredentialsProvider()
    {
        return [
            'Incorrect Email' => [
                'email' => 'guest@gmail.com',
                'password' => 'password',
                'expectedCode' => Response::HTTP_OK,
                'expectedErrorMessage' => 'Please try again! Those credentials do not match our records.',
            ],
            'Incorrect Password' => [
                'email' => 'john.doe@gmail.com',
                'password' => 'pass1234',
                'expectedCode' => Response::HTTP_OK,
                'expectedErrorMessage' => 'Please try again! Those credentials do not match our records.',
            ],
            'Incorrect Email And Password' => [
                'email' => 'guest@gmail.com',
                'password' => 'pass1234',
                'expectedCode' => Response::HTTP_OK,
                'expectedErrorMessage' => 'Please try again! Those credentials do not match our records.',
            ],
            'Empty Password' => [
                'email' => 'email@gmail.com',
                'password' => '',
                'expectedCode' => Response::HTTP_BAD_REQUEST,
                'expectedErrorMessage' => 'The password field is required.',
            ],
            'Empty Email' => [
                'email' => '',
                'password' => 'unchecked-password',
                'expectedCode' => Response::HTTP_BAD_REQUEST,
                'expectedErrorMessage' => 'The email field is required.',
            ],
            'Empty Email and Empty Password' => [
                'email' => '',
                'password' => '',
                'expectedCode' => Response::HTTP_BAD_REQUEST,
                'expectedErrorMessage' => 'The email field is required.  The password field is required.',
            ],
            'Password is null' => [
                'email' => 'email@gmail.com',
                'password' => null,
                'expectedCode' => Response::HTTP_BAD_REQUEST,
                'expectedErrorMessage' => 'The password field is required.',
            ],
            'Email is null' => [
                'email' => null,
                'password' => 'password',
                'expectedCode' => Response::HTTP_BAD_REQUEST,
                'expectedErrorMessage' => 'The email field is required.',
            ],
            'Email and Password are null' => [
                'email' => null,
                'password' => null,
                'expectedCode' => Response::HTTP_BAD_REQUEST,
                'expectedErrorMessage' => 'The email field is required.  The password field is required.',
            ],
            'Email is null and Password is Empty' => [
                'email' => null,
                'password' => '',
                'expectedCode' => Response::HTTP_BAD_REQUEST,
                'expectedErrorMessage' => 'The email field is required.  The password field is required.',
            ],
            'Email is empty and Password is null' => [
                'email' => '',
                'password' => null,
                'expectedCode' => Response::HTTP_BAD_REQUEST,
                'expectedErrorMessage' => 'The email field is required.  The password field is required.',
            ],
            'Malformed Email' => [
                'email' => 'email',
                'password' => 'password',
                'expectedCode' => Response::HTTP_BAD_REQUEST,
                'expectedErrorMessage' => 'The email must be a valid email address.',
            ],
            'Malformed Email and Password is Empty' => [
                'email' => 'no good email',
                'password' => '',
                'expectedCode' => Response::HTTP_BAD_REQUEST,
                'expectedErrorMessage' => 'The email must be a valid email address.  The password field is required.',
            ],
        ];
    }

    /**
     * Setup method for creating a user
     *
     * @return User instance
     */
    public function createUserSetup()
    {
        return User::factory()->create(
            [
                'name' => 'John Doe',
                'email' => 'john.doe@gmail.com',
                'password' => 'password',
            ]
        );
    }
}
