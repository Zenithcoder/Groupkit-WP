<?php

namespace Tests\Unit\app\Http\Controllers\Traits;

use App\Http\Controllers\Traits\GroupkitControllerBehavior;
use App\User;
use Illuminate\Http\Request;
use ReflectionClass;
use ReflectionException;
use stdClass;
use Tests\TestCase;
use Tests\TestHelper;

/**
 * Class GroupkitControllerBehaviorTest
 * represents unit test cover for {@see \App\Http\Controllers\Traits\GroupkitControllerBehavior}
 *
 * @package Tests\Feature\Unit\app\Http\Controllers\Traits
 * @coversDefaultClass \App\Http\Controllers\Traits\GroupkitControllerBehavior
 */
class GroupkitControllerBehaviorTest extends TestCase
{
    /**
     * @test
     * that __construct:
     * 1. sets the current user as an internal property via a deferred middleware callback
     * {@see \App\Http\Controllers\Traits\GroupkitControllerBehavior::$currentUser}
     * 2. sets provided request as internal property
     * {@see \App\Http\Controllers\Traits\GroupkitControllerBehavior::$request}
     * 3. calls init method
     *
     * @covers \App\Http\Controllers\Traits\GroupkitControllerBehavior::__construct
     * @throws ReflectionException
     */
    public function __construct_always_setsInternalProperties()
    {
        $requestMock = $this->createMock(Request::class);
        $guardType = null;

        $currentMock = $this->getMockBuilder('\App\Http\Controllers\Traits\GroupkitControllerBehavior')
            ->onlyMethods(['init'])
            ->addMethods(['middleware'])
            ->disableOriginalConstructor()
            ->setConstructorArgs([$requestMock])
            ->getMockForTrait();
        $userMock = $this->createMock(User::class);

        $requestMock->expects(static::once())->method('user')->with($guardType)->willReturn($userMock);
        $currentMock->expects(static::once())->method('middleware')->willReturnCallback(
            function (callable $callback) use ($requestMock) {
                $nextMiddlewareMock = $this->getMockBuilder(stdClass::class)
                    ->disableAutoload()
                    ->disableOriginalConstructor()
                    ->addMethods(['next'])
                    ->getMock();
                $nextMiddlewareMock->expects(static::once())->method('next')->with($requestMock)->willReturnSelf();
                $this->assertEquals(
                    $nextMiddlewareMock,
                    $callback($requestMock, [$nextMiddlewareMock, 'next'])
                );
            }
        );

        $currentMock->expects(static::once())->method('init');
        $reflectedClass = new ReflectionClass($currentMock);
        $constructor = $reflectedClass->getConstructor();
        $constructor->invoke($currentMock, $requestMock);
        $this->assertEquals($requestMock, TestHelper::getNonPublicProperty($currentMock, 'request'));
        $this->assertEquals($userMock, TestHelper::getNonPublicProperty($currentMock, 'currentUser'));
    }

    /**
     * @test
     * that getAjaxValidatorRules returns array of validation rules for action name if provided,
     * otherwise returns default rules
     *
     * @covers \App\Http\Controllers\Traits\GroupkitControllerBehavior::getAjaxValidatorRules
     *
     * @dataProvider getAjaxValidatorRules_withActionNamesProvider
     *
     * @param string|null $actionName of the validated method
     * @param array $validationRules for the provided action name, key represents property title and
     *                                                             value array of rules for the key
     * @param array $expectedResult of the tested method call
     *
     * @throws ReflectionException if ajaxValidatorRules property doesn't exist
     */
    public function getAjaxValidatorRules_withVariousActionNamesAndValidatorRules_returnsValidationRulesForActionName(
        ?string $actionName,
        array $validationRules,
        array $expectedResult
    ) {
        $currentMock = $this->getMockBuilder(GroupkitControllerBehavior::class)
            ->disableOriginalConstructor()
            ->getMockForTrait();

        /* Sets default validation rules */
        TestHelper::setNonPublicProperty($currentMock, 'ajaxValidatorRules', ['user_id' => 'required']);

        if ($actionName) {
            TestHelper::setNonPublicProperty(
                $currentMock,
                'ajaxValidatorRules',
                [$actionName => $validationRules]
            );
        }

        $this->assertEquals($expectedResult, $currentMock->getAjaxValidatorRules($actionName));
    }

    /**
     * Data provider for
     * @see getAjaxValidatorRules_withVariousActionNamesAndValidatorRules_returnsValidationRulesForActionName
     *
     * @return array[] containing
     * action name for validation rules,
     * validation rules for current action name
     * expected result of the tested method call
     */
    public function getAjaxValidatorRules_withActionNamesProvider()
    {
        return [
            [
                'actionName' => null,
                'validationRules' => [],
                'expectedResult' => ['user_id' => 'required'],
            ],
            [
                'actionName' => 'store',
                'validationRules' => ['name' => 'required'],
                'expectedResult' => ['name' => 'required'],
            ],
            [
                'actionName' => 'index',
                'validationRules' => [],
                'expectedResult' => [],
            ],
        ];
    }

    /**
     * @test
     * that init is only a placeholder in this trait
     *
     * @covers \App\Http\Controllers\Traits\GroupkitControllerBehavior::init
     *
     * @throws ReflectionException if init method is not defined
     */
    public function init_byDefault_isOnlyAPlaceholder()
    {
        $currentMock = $this->getMockBuilder(GroupkitControllerBehavior::class)
            ->disableOriginalConstructor()
            ->getMockForTrait();

        $this->assertNull(TestHelper::callNonPublicFunction($currentMock, 'init'));
    }
}
