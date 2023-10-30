<?php

namespace Database\Factories;

use App\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Class UserFactory for create User data for testing purpose
 * @package Database\Factories
 */
class UserFactory extends Factory
{
    /**
     * @var string The name of the factory's corresponding model.
     */
    protected $model = User::class;

    /**
     * Define the model's default state.
     *
     * @return array containing User {@see User} data
     */
    public function definition()
    {
        return [
            'name' => str_replace("'", "", $this->faker->name), #removes ' from faked names
            'email' => $this->faker->unique()->safeEmail,
            'email_verified_at' => now(),
            'password' => Hash::make('password'),
            'remember_token' => Str::random(10),
            'timezone' => 'Europe/Belgrade',
            'stripe_id' => uniqid('cus_'),
            'stripe_account' => 'default',
        ];
    }
}
