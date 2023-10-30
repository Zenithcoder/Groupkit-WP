<?php

namespace Tests\Unit\app;

use App\Http\Middleware\TeamUser;
use App\Mail\UpdateEmail;
use App\EmailUpdateRequest;
use App\User;
use Exception;
use Faker\Factory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Class EmailUpdateRequestTest adds test coverage for {@see EmailUpdateRequest}
 *
 * @package Tests\Unit\app
 * @coversDefaultClass \App\EmailUpdateRequest
 */
class EmailUpdateRequestTest extends TestCase
{
    use DatabaseTransactions;
    use DatabaseMigrations;

    /**
     * @test
     * that sendActivationLink returns error message when user passed already occupied email address
     *
     * @covers ::sendActivationLink
     */
    public function sendActivationLink_withOccupiedEmailAddress_returnsErrorMessage()
    {
        $this->withoutMiddleware(TeamUser::class);
        $existingUser = $this->actingAsUser();
        $faker = Factory::create();
        Mail::fake();
        $clientIp = $faker->unique()->localIpv4;

        $newUser = User::factory(['email' => 'jennySmith@gmail.com'])->create();

        $response = (new EmailUpdateRequest())->sendActivationLink($newUser->email, $existingUser->email, $clientIp);

        $this->assertFalse($response['success']);
        $this->assertEquals('There is already an account using this email address.', $response['message']);

        Mail::assertNothingSent();
    }

    /**
     * @test
     * that sendActivationLink sends email to the new address and returns success message
     *
     * @covers ::sendActivationLink
     */
    public function sendActivationLink_withValidNewEmailAddress_returnsSuccessResponse()
    {
        $this->withoutMiddleware(TeamUser::class);
        $user = $this->actingAsUser();
        $faker = Factory::create();
        $clientIp = $faker->unique()->localIpv4;
        Mail::fake();
        $newEmail = 'test@gmail.com';

        $response = (new EmailUpdateRequest())->sendActivationLink($user->email, $newEmail, $clientIp);

        $this->assertTrue($response['success']);
        $this->assertEquals(
            "Confirmation email has been sent to your new email address $newEmail",
            $response['message']
        );
        $this->assertDatabaseHas('email_update_requests', [
            'current_email' => $user->email,
            'new_email' => $newEmail,
        ]);
        Mail::assertSent(UpdateEmail::class);
    }

    /**
     * @test
     * that sendActivationLink failed to create email Update requests and returns invalid response message
     *
     * @covers ::sendActivationLink
     */
    public function sendActivationLink_whenExceptionIsThrown_returnsInvalidResponse()
    {
        $this->withoutMiddleware(TeamUser::class);
        $user = $this->actingAsUser();
        $faker = Factory::create();
        $clientIp = $faker->unique()->localIpv4;
        Mail::fake();
        $newEmail = 'test@gmail.com';
        $userMock = $this->mock(User::class);
        $userMock->shouldReceive('where')->with('email', $user->email)->andReturnSelf();
        $userMock->shouldReceive('first')->andReturn($currentMock = $this->createMock(User::class));
        $currentMock->setAttribute('email', ['ffsfsd']);

        $this->app->instance(User::class, $userMock);
        $response = (new EmailUpdateRequest())->sendActivationLink($user->email, $newEmail, $clientIp);

        $this->assertEquals("Invalid Request", $response['message']);
        $this->assertFalse($response['success']);
        $this->assertDatabaseMissing('email_update_requests', [
            'current_email' => $user->email,
            'new_email' => $newEmail,
        ]);
        Mail::assertNothingSent();
    }
}
