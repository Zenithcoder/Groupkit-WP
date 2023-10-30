<?php

namespace Tests\Unit\app\Http\Controllers;

use App\Http\Controllers\HomeController;
use App\Http\Middleware\TeamUser;
use App\Mail\UpdateEmail;
use App\EmailUpdateRequest;
use App\User;
use Exception;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;
use Stripe\Price;

/**
 * Class HomeControllerTest adds test coverage for {@see HomeController}
 *
 * @package Tests\Unit\app\Http\Controllers
 * @coversDefaultClass \App\Http\Controllers\HomeController
 */
class HomeControllerTest extends TestCase
{
    use DatabaseMigrations;
    use DatabaseTransactions;

    /**
     * @test
     * that init protects routes from unauthorized users and returns them to the login
     *
     * @covers ::init
     *
     * @dataProvider init_withVariousRoutesProvider
     *
     * @param string $route name for the request
     */
    public function init_withVariousRoutes_redirectsToLogin(string $route)
    {
        $response = $this->get(route($route));

        $response->assertRedirect('login');
        $response->assertStatus(Response::HTTP_FOUND);
    }

    /**
     * Data provider for {@see init_withVariousRoutes_redirectsToLogin}
     *
     * @return array[] containing route name
     */
    public function init_withVariousRoutesProvider()
    {
        return [
            ['route' => 'giveaway'],
            ['route' => 'gkthanks'],
            ['route' => 'setting'],
        ];
    }

    /**
     * @test
     * that init returns validation message according to the provided key and values
     *
     * @covers ::init
     *
     * @dataProvider init_withVariousRequestParamsForUpdateProvider
     *
     * @param string $requestType of the tested route
     * @param string $uri of the tested route
     * @param array $requestData containing key value pair params
     * @param string $expectedMessage of the tested method call
     */
    public function init_withVariousRequestParamsForUpdate_returnsValidationMessage(
        string $requestType,
        string $uri,
        array $requestData,
        string $expectedMessage
    ) {
        $this->withoutMiddleware(TeamUser::class);
        $this->actingAsUser();

        $response = $this->call($requestType, $uri, $requestData);

        $response->assertStatus(Response::HTTP_BAD_REQUEST);
        $response->assertJsonStructure(['message', 'data']);
        $response->assertJsonFragment([
            'message' => $expectedMessage,
            'data' => [],
        ]);
    }

    /**
     * Data provider for {@see init_withVariousRequestParamsForUpdate_returnsValidationMessage}
     *
     * @return array[] containing route request type, uri of the route, request data
     * and expected message and code of the tested method call
     */
    public function init_withVariousRequestParamsForUpdateProvider(): array
    {
        return [
            # Validation test cases for update method
            'The both first name and last name field is required for update method' => [
                'requestType' => 'POST',
                'uri' => 'user/update',
                'requestData' => [],
                'expectedMessage' => 'The first name field is required.  The last name field is required.',
            ],
            'The first name field is required for update method' => [
                'requestType' => 'POST',
                'uri' => 'user/update',
                'requestData' => [
                    'last_name' => 'Smith',
                ],
                'expectedMessage' => 'The first name field is required.',
            ],
            'The last name field is required for update method' => [
                'requestType' => 'POST',
                'uri' => 'user/update',
                'requestData' => [
                    'first_name' => 'Mark',
                ],
                'expectedMessage' => 'The last name field is required.',
            ],
            'The password field required minimum 8 chars and also should match confirm password for update method' => [
                'requestType' => 'POST',
                'uri' => 'user/update',
                'requestData' => [
                    'first_name' => 'Mark',
                    'last_name' => 'Smith',
                    'password' => 'Pass',
                ],
                'expectedMessage' =>
                    'The password must be at least 8 characters.  The password and confirmed must match.',
            ],
            'The password and confirm password are not same for update method' => [
                'requestType' => 'POST',
                'uri' => 'user/update',
                'requestData' => [
                    'first_name' => 'Mark',
                    'last_name' => 'Smith',
                    'password' => 'Password',
                    'confirmed' => 'Wordpass',
                ],
                'expectedMessage' => 'The password and confirmed must match.',
            ],
        ];
    }

    /**
     * @test
     * that giveaway always returns giveaway view
     *
     * @covers ::giveaway
     */
    public function giveaway_always_returnsGiveAwayView()
    {
        $this->actingAsUser();

        $response = $this->get(route('giveaway'));

        $response->assertOk();
        $response->assertViewIs('giveaway');
    }

    /**
     * @test
     * that wait returns wait view and displays session items
     *
     * @covers ::wait
     */
    public function wait_always_returnsWaitView()
    {
        $user = $this->actingAsUser();

        $sessionData = [
            'paymentMethod' => 'Subscription',
            'purchase' => 'on',
            'requestUser' => [
                'firstName' => Str::before($user->name, ' '),
                'lastName' => Str::after($user->name, ' '),
                'email' => $user->email,
                'password' => $user->password,
                'userData' => 'Stubbed User Data'
            ],
        ];

        session()->put('token', base64_encode(json_encode($sessionData)));
        $response = $this->get(route('wait'));

        $response->assertOk();
        $response->assertViewIs('wait');

        foreach ($sessionData['requestUser'] as $item) {
            $response->assertSee($item);
        }
        $response->assertSee($sessionData['paymentMethod']);
        $response->assertSee($sessionData['purchase']);
    }

    /**
     * @test
     * that gkthanks always returns gkthanks view
     *
     * @covers ::gkthanks
     */
    public function gkthanks_always_returnsGKThanksView()
    {
        $this->actingAsUser();

        $response = $this->get(route('gkthanks'));

        $response->assertOk();
        $response->assertViewIs('gkthanks');
    }

    /**
     * @test
     * that index always returns home view
     *
     * @covers ::index
     */
    public function index_always_returnsHomeView()
    {
        $this->withoutMiddleware(TeamUser::class);
        $user = $this->actingAsUser();

        $response = $this->get(route('home', $user));

        $response->assertOk();
        $response->assertViewIs('home');
    }

    /**
     * @test
     * that setting always returns setting view with logged in user
     *
     * @covers ::setting
     */
    public function setting_always_returnsSettingView()
    {
        $this->withoutMiddleware(TeamUser::class);
        $user = User::factory()->create(['stripe_id' => null]);
        $this->actingAs($user);

        $response = $this->get(route('setting', $user));

        $response->assertViewHas('user');
        $response->assertOk();
        $response->assertViewIs('setting');
    }

    /**
     * @test
     * that update successfully updates user data and returns {@see Response::HTTP_OK} response
     *
     * @covers ::update
     */
    public function update_withoutUpdateOnlyTimeZone_updatesUser()
    {
        $this->withoutMiddleware(TeamUser::class);
        $user = $this->actingAsUser();

        $requestData = [
            'timeZone'   => 'Asia/Calcutta',
            'password'   => '12345678',
            'confirmed'  => '12345678',
            'email'      => $user->email,
            'first_name' => 'testFirst',
            'last_name'  => 'testLast',
        ];

        $response = $this->post('user/update', $requestData);

        $response->assertOk();
        $response->assertJsonFragment(['status' => 'success']);
        $this->assertDatabaseHas('users', [
            'id'       => $user->id,
            'timezone' => $requestData['timeZone'],
            'name'     => "{$requestData['first_name']} {$requestData['last_name']}",
        ]);
    }

    /**
     * @test
     * that update updates only user timezone when updateOnlyTimeZone param is true
     *
     * @covers ::init
     * @covers ::update
     */
    public function update_withUpdateOnlyTimeZone_updatesOnlyUserTimezone()
    {
        $this->withoutMiddleware(TeamUser::class);
        $user = $this->actingAsUser();

        $requestData = [
            'updateOnlyTimeZone' => true,
            'timeZone'   => 'Asia/Calcutta',
            'password'   => '12345678',
            'confirmed'  => '12345678',
            'email'      => $user->email,
            'first_name' => 'testFirst',
            'last_name'  => 'testLast',
        ];

        $response = $this->post('user/update', $requestData);

        $response->assertOk();
        $response->assertJsonFragment(['status' => 'success']);
        $this->assertDatabaseHas('users', [
            'id'       => $user->id,
            'timezone' => $requestData['timeZone'],
            'name'     => $user->name,
        ]);
        $this->assertDatabaseMissing('users', [
            'id'       => $user->id,
            'timezone' => $user->timezone,
            'email'    => $user->email,
            'name'     => "{$requestData['first_name']} {$requestData['last_name']}",
        ]);
    }

    /**
     * @test
     * that update doesn't updates user data when throws an exception
     *
     * @covers ::update
     */
    public function update_whenThrowsAnException_doesntUpdatesUser()
    {
        $this->withoutMiddleware(TeamUser::class);
        $this->actingAsUser();

        $userMock = $this->partialMock(User::class);
        $userMock->shouldReceive('update')->andThrow(new Exception());
        $this->actingAs($userMock);

        $requestData = [
            'timeZone' => 'Asia/Calcutta',
            'password' => '12345678',
            'confirmed' => '12345678',
            'email' => 'john.doe@gmail.com',
            'first_name' => 'lynda',
            'last_name' => 'smith',
        ];

        $response = $this->post('user/update', $requestData);

        $response->assertOk();
        $response->assertJsonFragment([
            'status'  => 'error',
            'message' => 'Unable To Updated Successfully.',
            'data'    => [],
        ]);
        $this->assertDatabaseMissing('users', [
            'email'    => $requestData['email'],
            'name'     => "{$requestData['first_name']} {$requestData['last_name']}",
            'timezone' => $requestData['timeZone'],
        ]);
    }

    /**
     * @test
     * that sendNewEmailActivationLink returns validation message according to the various situations
     *
     * @covers ::sendNewEmailActivationLink
     *
     * @dataProvider sendNewEmailActivationLink_withVariousEmailsProvider
     *
     * @param string $email address that the customer want the update to
     * @param string $expectedMessage of the tested method call
     */
    public function sendNewEmailActivationLink_withVariousEmails_returnsValidationMessage(
        string $email,
        string $expectedMessage
    ) {
        $this->withoutMiddleware(TeamUser::class);
        $this->actingAsUser();
        Mail::fake();

        $response = $this->post('user/sendNewEmailActivationLink', ['email' => $email]);

        $response->assertStatus(Response::HTTP_BAD_REQUEST);
        $response->assertJsonStructure(['message', 'data']);
        $response->assertJsonFragment([
            'message' => $expectedMessage,
            'data'    => [],
        ]);
        Mail::assertNothingSent();
    }

    /**
     * Data provider for {@see sendNewEmailActivationLink_withVariousEmails_returnsValidationMessage}
     *
     * @return array[] containing email of new email address, expected message
     * and code of the tested method call
     */
    public function sendNewEmailActivationLink_withVariousEmailsProvider(): array
    {
        # Validation test cases for update method
        return [
            'Email Required'                   => [
                'email'           => '',
                'expectedMessage' => 'The email field is required.',
            ],
            'Email is Invalid'                 => [
                'email'           => '1111',
                'exceptedMessage' => 'The email must be a valid email address.',
            ],
            'Max 100 Characters Email Address' => [
                'email'           => 'loremTestJob_loremTestJob_loremTestJob_loremTestJob_loremTestJob_' .
                    'loremTestJobloremTestJobloremTestJobloremTestJob@gmail.com',
                'exceptedMessage' => 'The email may not be greater than 100 characters.',
            ],
        ];
    }

    /**
     * @test
     * that sendNewEmailActivationLink returns an error message if the user passed the existing user's email address
     *
     * @covers ::sendNewEmailActivationLink
     */
    public function sendNewEmailActivationLink_withSameEmailAddress_returnErrorResponse()
    {
        $this->withoutMiddleware(TeamUser::class);
        $user = $this->actingAsUser();
        Mail::fake();

        $response = $this->post('user/sendNewEmailActivationLink', ['email' => $user->email]);

        $response->assertStatus(Response::HTTP_BAD_REQUEST);
        $response->assertJsonStructure(['message']);
        $response->assertJsonFragment([
            'message' => "The email has already been taken."
        ]);
        Mail::assertNothingSent();
    }

    /**
     * @test
     * that sendNewEmailActivationLink sends email to the new address and returns success message
     *
     * @covers ::sendNewEmailActivationLink
     */
    public function sendNewEmailActivationLink_withValidNewEmailAddress_returnsSuccessResponse()
    {
        $this->withoutMiddleware(TeamUser::class);
        $this->actingAsUser();
        Mail::fake();
        $newEmail = 'test@gmail.com';

        $response = $this->post('user/sendNewEmailActivationLink', ['email' => $newEmail]);

        $response->assertOk();
        $response->assertJsonStructure(['message']);
        $response->assertJsonFragment([
            'message' => "Confirmation email has been sent to your new email address $newEmail"
        ]);
        Mail::assertSent(UpdateEmail::class);
    }

    /**
     * @test
     * that sendNewEmailActivationLink returns an error message when user passed already registered users email address.
     *
     * @covers ::sendNewEmailActivationLink
     */
    public function sendNewEmailActivationLink_withExitsEmailRequest_returnsErrorResponse()
    {
        $this->withoutMiddleware(TeamUser::class);
        $user = $this->actingAsUser();
        Mail::fake();
        User::factory(['email' => 'jennySmith@gmail.com'])->create();

        $response = $this->post('user/sendNewEmailActivationLink', ['email' => $user->email]);

        $response->assertStatus(Response::HTTP_BAD_REQUEST);
        $response->assertJsonStructure(['message']);
        $response->assertJsonFragment([
            'message' =>
                'The email has already been taken.'
        ]);

        $this->assertDatabaseMissing('email_update_requests', [
            'current_email' => 'jennySmith@gmail.com',
            'new_email' => $user->email,
        ]);
        Mail::assertNothingSent();
    }

    /**
     * @test
     * that activateNewEmail returns view with success message, when user passed valid activation code
     *
     * @covers ::activateNewEmail
     */
    public function activateNewEmail_withValidActivationCode_returnsViewWithSuccessMessage()
    {
        $this->withoutMiddleware(TeamUser::class);
        $user = User::factory()->create(['stripe_id' => null]);
        $this->actingAs($user);

        $updateEmail = EmailUpdateRequest::factory([
            'current_email' => $user->email,
            'new_email' => 'test@gmail.com',
            'activation_code' => Crypt::encryptString('test@gmail.com' . rand())
        ])->create();

        $response = $this->get(route('user.activateNewEmail', $updateEmail->activation_code));

        $response->assertOk();
        $response->assertViewIs('update-email-message');
        $response->assertSee('Your email address has been updated successfully.');
    }

    /**
     * @test
     * that activateNewEmail returns view with error message when user passed invalid activation code
     *
     * @covers ::activateNewEmail
     */
    public function activateNewEmail_withInvaliedActivationCode_returnsViewWithErrorMessage()
    {
        $this->withoutMiddleware(TeamUser::class);
        $user = $this->actingAsUser();

        EmailUpdateRequest::factory([
            'current_email' => $user->email,
            'new_email' => 'test@gmail.com',
            'activation_code' => Crypt::encryptString('test@gmail.com' . rand())
        ])->create();

        $response = $this->get(route('user.activateNewEmail', Crypt::encryptString('1234FDS')));

        $response->assertOk();
        $response->assertViewIs('update-email-message');
        $response->assertSee('This link has expired.');
    }

    /**
     * @test
     * that activateNewEmail returns view with error message when user passed already occupied email address
     *
     * @covers ::activateNewEmail
     */
    public function activateNewEmail_withOccupiedEmailAddress_returnsViewWithErrorMessage()
    {
        $this->withoutMiddleware(TeamUser::class);
        $existingUser = $this->actingAsUser();

        $newUser = User::factory(['email' => 'jennySmith@gmail.com'])->create();

        $updateEmail = EmailUpdateRequest::factory([
            'current_email' => $newUser->email,
            'new_email' => $existingUser->email,
            'activation_code' => Crypt::encryptString($existingUser->email . rand())
        ])->create();

        $response = $this->get(route('user.activateNewEmail', $updateEmail->activation_code));

        $response->assertOk();
        $response->assertViewIs('update-email-message');
        $response->assertSee('There is already an account using this email address.');
    }

    /**
     * @test
     * that activateNewEmail returns view with success message, when user passed valid activation code & stripe id
     *
     * @covers ::activateNewEmail
     */
    public function activateNewEmail_withValidActivationCodeAndStripeId_returnsViewWithSuccessMessage()
    {
        $this->withoutMiddleware(TeamUser::class);
        $user = User::factory()->create(['stripe_id' => 'cus_JWDKsUR0rv1Y1Q']);

        $updateEmail = EmailUpdateRequest::factory([
            'current_email' => $user->email,
            'new_email' => 'test@gmail.com',
            'activation_code' => Crypt::encryptString('test@gmail.com' . rand())
        ])->create();

        $response = $this->get(route('user.activateNewEmail', $updateEmail->activation_code));

        $response->assertOk();
        $response->assertViewIs('update-email-message');
        $response->assertSee('Your email address has been updated successfully.');
    }

    /**
     * @test
     * that activateNewEmail returns view with error message, when users current email address is not found, or
     * current email address has been changed with other activate New Email request
     *
     * @covers ::activateNewEmail
     */
    public function activateNewEmail_withUnknownEmailAddress_returnsViewWithErrorMessage()
    {
        $this->withoutMiddleware(TeamUser::class);
        $existingUser = $this->actingAsUser();

        $updateEmail = EmailUpdateRequest::factory([
            'current_email' => $existingUser->email,
            'new_email' => 'test@gmail.com',
            'activation_code' => Crypt::encryptString('test@gmail.com' . rand())
        ])->create();

        $user = User::find($existingUser->id);
        $user->email = 'userIsNotExists@gmail.com';
        $user->save();

        $response = $this->get(route('user.activateNewEmail', $updateEmail->activation_code));

        $response->assertOk();
        $response->assertViewIs('update-email-message');
        $response->assertSee('User is not exists in system.');
    }

    /**
     * @test
     * that activateNewEmail returns view with error message, when user passed valid activation code & invalid stripe id
     *
     * @covers ::activateNewEmail
     */
    public function activateNewEmail_withValidActivationCodeAndInavalidStripeId_returnsViewWithErrorMessage()
    {
        $this->withoutMiddleware(TeamUser::class);
        $user = User::factory()->create(['stripe_id' => 'cus_JWDKsUR']);

        $updateEmail = EmailUpdateRequest::factory([
            'current_email' => $user->email,
            'new_email' => 'test@gmail.com',
            'activation_code' => Crypt::encryptString('test@gmail.com' . rand())
        ])->create();

        $response = $this->get(route('user.activateNewEmail', $updateEmail->activation_code));

        $response->assertOk();
        $response->assertViewIs('update-email-message');
        $response->assertSee('Something went wrong');
    }

    /**
     * @test
     * that noGroupsAssigned always returns no-groups-assigned view with proper content
     *
     * @covers ::noGroupsAssigned
     */
    public function noGroupsAssigned_always_returnsNoGroupsAssignedView()
    {
        $this->actingAsUser();

        $response = $this->get(route('noGroupsAssigned'));

        $response->assertOk();
        $response->assertViewIs('errors.no-groups-assigned');
        $response->assertSee('You must be assigned a group from the account administrator before you can use GroupKit');
    }
}
