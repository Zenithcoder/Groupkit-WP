<?php

namespace Tests\Unit\app\Http\Controllers\Auth;

use App\Providers\RouteServiceProvider;
use App\Services\TapfiliateService;
use App\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

/**
 * Class RegisterControllerTest adds test coverage for {@see \App\Http\Controllers\Auth\RegisterController} class
 *
 * @package Tests\Unit\app\Http\Controllers\Auth
 * @coversDefaultClass \App\Http\Controllers\Auth\RegisterController
 */
class RegisterControllerTest extends TestCase
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
     * that init protects all methods from authenticated users
     *
     * @covers ::init
     *
     * @dataProvider init_withVariousRoutesProvider
     *
     * @param string $requestType of the tested route
     * @param string $url of the tested route
     */
    public function init_withVariousRoutes_redirectsToLogin(
        string $requestType,
        string $url
    ) {
        $this->actingAs(User::factory()->create());

        $response = $this->call($requestType, $url);

        $this->assertAuthenticated();
        $this->assertEquals(Response::HTTP_FOUND, $response->getStatusCode());
        $response->assertRedirect(route('home'));
    }

    /**
     * Data provider for {@see init_withVariousRoutes_redirectsToLogin}
     *
     * @return array[] containing request type and url for the tested route
     */
    public function init_withVariousRoutesProvider(): array
    {
        return [
            ['requestType' => 'GET', 'url' => 'register'],
            ['requestType' => 'POST', 'url' => 'register'],
        ];
    }

    /**
     * @test
     * that validator returns error in session if post data is invalid
     *
     * @covers ::validator
     *
     * @dataProvider validator_withVariousPostRequestsProvider
     *
     * @param array $requestData containing POST request items
     *                           {first_name, last_name, email, password, password_confirmation}
     * @param array $expectedValidationFields of the tested method call
     */
    public function validator_withVariousPostRequests_returnsValidationErrors(
        array $requestData,
        array $expectedValidationFields
    ) {
        $response = $this->post(route('register', $requestData));

        $this->assertGuest();
        $response->assertRedirect('/');
        $response->assertSessionHasErrors($expectedValidationFields);
    }

    /**
     * Data provider for {@see validator_withVariousPostRequests_returnsValidationErrors}
     *
     * @return array[] containing request data and expected validation fields
     */
    public function validator_withVariousPostRequestsProvider(): array
    {
        return [
            'requestedFields'                        => [
                'requestData'               => [],
                'expectedValidationFields' => [
                    'first_name',
                    'last_name',
                    'email',
                    'password',
                ],
            ],
            'FieldNeedToBeString'                    => [
                'requestData'               => [
                    'first_name' => 1,
                    'last_name'  => 1,
                    'email'      => 1,
                    'password'   => 1,
                ],
                'expectedValidationFields' => [
                    'first_name',
                    'last_name',
                    'email',
                    'password',
                ],
            ],
            'FirstAndLastNameMax32Characters'        => [
                'requestData'               => [
                    'first_name'            => Str::random(33),
                    'last_name'             => Str::random(33),
                    'email'                 => 'john.doe@gmail.com',
                    'password'              => 'password123',
                    'password_confirmation' => 'password123',
                ],
                'expectedValidationFields' => [
                    'first_name',
                    'last_name',
                ],
            ],
            'ValidEmail'                             => [
                'requestData'               => [
                    'first_name'            => 'john',
                    'last_name'             => 'doe',
                    'email'                 => 'john.doeail.com',
                    'password'              => 'password123',
                    'password_confirmation' => 'password123',
                ],
                'expectedValidationFields' => [
                    'email',
                ],
            ],
            'PasswordNeedsToBeConfirmed'             => [
                'requestData'               => [
                    'first_name'            => 'john',
                    'last_name'             => 'doe',
                    'email'                 => 'john.doe@gmail.com',
                    'password'              => 'password123',
                    'password_confirmation' => 'password13',
                ],
                'expectedValidationFields' => [
                    'password',
                ],
            ],
            'EmailFieldLengthIsMax100Characters'     => [
                'requestData'               => [
                    'first_name'            => 'john',
                    'last_name'             => 'doe',
                    'email'                 => Str::random(91) . '@gmail.com',
                    'password'              => 'password123',
                    'password_confirmation' => 'password123',
                ],
                'expectedValidationFields' => [
                    'email',
                ],
            ],
            'PasswordsLengthNeedsToBeMin8Characters' => [
                'requestData'               => [
                    'first_name'            => 'john',
                    'last_name'             => 'doe',
                    'email'                 => 'john@gmail.com',
                    'password'              => 'passwor',
                    'password_confirmation' => 'passwor',
                ],
                'expectedValidationFields' => [
                    'password',
                ],
            ],
        ];
    }

    /**
     * @test
     * that create:
     * 1. Creates a user in the database
     * 2. Authenticates the user in session
     * 3. Stores groupkit auth data in session
     *
     * @covers ::create
     */
    public function create_always_redirectsToHome()
    {
        $this->artisan('passport:install');
        $requestData = [
            'first_name' => 'John',
            'last_name'  => 'John',
            'email' => 'john.doe@gmail.com',
            'password' => 'Password123',
            'password_confirmation' => 'Password123',
        ];

        $response = $this->post(route('register', $requestData));

        $this->assertAuthenticated();
        $response->assertStatus(Response::HTTP_FOUND);
        $response->assertRedirect(RouteServiceProvider::HOME);
        $this->assertDatabaseHas('users', [
            'email' => $requestData['email'],
            'name' => $requestData['first_name'] . " " . $requestData['last_name'],
        ]);
        $response->assertSessionHas('groupkit_auth');
        $response->assertSessionDoesntHaveErrors();
    }

    /**
     * @test
     * that showRegistrationForm redirects to the plans page
     *
     * @covers ::showRegistrationForm
     */
    public function showRegistrationForm_withoutTapfiliateParameter_redirectsToPlansPage()
    {
        $response = $this->get('register');

        $this->assertGuest();
        $response->assertStatus(Response::HTTP_FOUND);
        $response->assertRedirect(route('plans.index'));
    }

    /**
     * @test
     * that showRegistrationForm:
     * 1. redirects to the plans page
     * 2. stores tapfiliate parameter in cookie if is present in the request
     *    as {@see TapfiliateService::TAPFILIATE_REQUEST_PARAMETER}
     *
     * @covers ::showRegistrationForm
     */
    public function showRegistrationForm_withTapfiliateParameter_storesTapfiliateParameterInCookie()
    {
        $tapfiliateValue = 'customers_ref_code';

        $response = $this->get(
            route('register', [TapfiliateService::TAPFILIATE_REQUEST_PARAMETER => $tapfiliateValue])
        );

        $this->assertGuest();
        $response->assertStatus(Response::HTTP_FOUND);
        $response->assertRedirect(route('plans.index'));
        $response->assertCookie('tapfiliate_id', $tapfiliateValue);
    }
}
