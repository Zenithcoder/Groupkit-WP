<?php

namespace Tests\Unit\app\Http\Controllers\Auth;

use App\Http\Controllers\Auth\ConfirmPasswordController;
use ReflectionException;
use Tests\TestCase;
use Tests\TestHelper;

/**
 * Class ConfirmPasswordControllerTest adds test coverage for
 * {@see \App\Http\Controllers\Auth\ConfirmPasswordController} class
 *
 * @package Tests\Unit\app\Http\Controllers\Auth
 * @coversDefaultClass \App\Http\Controllers\Auth\ConfirmPasswordController
 */
class ConfirmPasswordControllerTest extends TestCase
{
    /**
     * @test
     * that init protects routes from unauthorized users and returns them to the login
     *
     * @covers ::init
     *
     * @throws ReflectionException if init method is not defined
     */
    public function init_always_setsMiddlewares()
    {
        $currentMock = $this->getMockBuilder(ConfirmPasswordController::class)
            ->addMethods([])
            ->disableOriginalConstructor()
            ->getMock();

        TestHelper::callNonPublicFunction($currentMock, 'init');

        $this->assertEquals('auth', $currentMock->getMiddleware()[0]['middleware']);
    }
}
