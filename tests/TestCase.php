<?php

namespace Tests;

use App\User;
use Illuminate\Database\Connection;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;
use Laravel\Passport\Passport;
use Mockery;
use Mockery\MockInterface;
use PDO;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    /**
     * @var MockInterface of the functions for the tested class
     */
    public static MockInterface $functions;

    /**
     * Setup test dependencies
     */
    protected function setUp(): void
    {
        parent::setUp();
        static::$functions = Mockery::mock();
    }

    /**
     * Sets the currently logged in user for the application.
     *
     * @return User that is logged in into the application
     */
    public function actingAsUser(): User
    {
        $user = User::factory()->create(
            [
                'name' => 'John Doe',
                'email' => 'john.doe@gmail.com',
                'password' => 'password',
            ]
        );

        $this->actingAs($user);

        return $user;
    }

    /**
     * Sets the currently logged in user for the API application.
     *
     * @return User that is logged in into the application
     */
    public function actingAsApiUser(): User
    {
        $user = User::factory()->create([
            'name' => 'John Doe',
            'email' => 'john.doe@gmail.com',
            'password' => 'password',
        ]);

        Passport::actingAs($user);

        return $user;
    }

    /**
     * Adds MySQL CONCAT() support to SQLite
     */
    public function addMySQLConcatFunction()
    {
        /** @var Connection $connection */
        $connection = DB::connection();
        $dbHandle = $connection->getPdo();

        if ('sqlite' === $dbHandle->getAttribute(PDO::ATTR_DRIVER_NAME)) {
            $dbHandle->sqliteCreateFunction(
                'concat',
                function (...$input) {
                    return implode('', $input);
                }
            );
        }
    }

    /**
     * Adds MySQL DATE_FORMAT() support to SQLite
     */
    public function addMySQLDATE_FORMATFunction()
    {
        /** @var Connection $connection */
        $connection = DB::connection();
        $dbHandle = $connection->getPdo();

        if ('sqlite' === $dbHandle->getAttribute(PDO::ATTR_DRIVER_NAME)) {
            $dbHandle->sqliteCreateFunction(
                'DATE_FORMAT',
                function () {
                    return strftime('%m %d %Y', 'created_at');
                }
            );
        }
    }
}

namespace App\Http\Controllers\API;

use Tests\TestCase;
use Tests\Unit\app\Http\Controllers\API\GroupControllerTest;

/**
 * Mock of the original {@see set_time_limit()} method
 *
 * @param int $value that will be provided to the mocked method
 *
 * @return mixed mock of the {@see GroupControllerTest}
 */
function set_time_limit(int $value)
{
    return TestCase::$functions->set_time_limit($value);
}
