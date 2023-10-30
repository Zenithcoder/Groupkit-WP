<?php

namespace Tests\Unit\App\Http\Controllers\Auth;

use App\Http\Controllers\Auth\ResetPasswordController;
use App\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use ReflectionException;
use Tests\TestCase;
use Tests\TestHelper;

/**
 * Class ResetPasswordControllerTest adds test coverage for {@see ResetPasswordController} class
 *
 * @package Tests\Unit\App\Http\Controllers\Auth
 * @coversDefaultClass \App\Http\Controllers\Auth\ResetPasswordController
 */
class ResetPasswordControllerTest extends TestCase
{
    use DatabaseTransactions;
    use DatabaseMigrations;

    /**
     * @test
     * that resetPassword sets new password for the user and logs in that user
     *
     * @covers ::resetPassword
     *
     * @throws ReflectionException if resetPassword method is not defined
     */
    public function resetPassword_always_resetsUserPassword()
    {
        $currentMock = $this->partialMock(ResetPasswordController::class)
            ->shouldAllowMockingProtectedMethods()
            ->shouldAllowMockingMethod('guard');
        $password = 'Password123';
        $accessToken = sha1('groupkit_access_token');
        $userMock = $this->getMockBuilder(User::class)
            ->onlyMethods(['forceFill', 'save', 'createToken'])
            ->getMock();
        $userMock->expects(static::once())->method('forceFill')->willReturnSelf();
        $userMock->expects(static::once())->method('save')->willReturnSelf();
        $userMock->expects(static::once())
            ->method('createToken')
            ->willReturn((object)['accessToken' => $accessToken]);

        $currentMock->shouldReceive('guard')->andReturnSelf();
        $currentMock->shouldReceive('login')->with($userMock);

        TestHelper::callNonPublicFunction($currentMock, 'resetPassword', [$userMock, $password]);

        $result = json_decode(base64_decode(session()->get('groupkit_auth')));

        $this->assertEquals($accessToken, $result->access_token);
    }
}
