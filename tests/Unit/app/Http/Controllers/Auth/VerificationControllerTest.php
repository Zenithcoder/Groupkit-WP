<?php

namespace Tests\Unit\app\Http\Controllers\Auth;

use App\Http\Controllers\Auth\VerificationController;
use ReflectionException;
use Tests\TestCase;
use Tests\TestHelper;

/**
 * Class VerificationControllerTest adds test coverage for
 * {@see \App\Http\Controllers\Auth\VerificationController} class
 *
 * @package Tests\Unit\app\Http\Controllers\Auth
 * @coversDefaultClass \App\Http\Controllers\Auth\VerificationController
 */
class VerificationControllerTest extends TestCase
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
        $currentMock = $this->getMockBuilder(VerificationController::class)
            ->addMethods([])
            ->disableOriginalConstructor()
            ->getMock();
        $expectedMiddlewares = [
            'auth',
            'signed',
            'throttle:6,1',
        ];

        TestHelper::callNonPublicFunction($currentMock, 'init');

        for ($i = 0; $i < $this->count($currentMock->getMiddleware()); $i++) {
            $this->assertEquals($expectedMiddlewares[$i], $currentMock->getMiddleware()[$i]['middleware']);
        }
    }
}
