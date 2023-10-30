<?php

namespace Database\Factories;

use App\PasswordResets;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Carbon\Carbon;

/**
 * Class PasswordResetsFactory creates Password Resets in the database for testing purposes
 *
 * @package Database\Factories
 */
class PasswordResetsFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = PasswordResets::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [
            'email' => 'test@gmail.com',
            'token' => Hash::make(Str::random(40)),
            'created_at' => Carbon::now(),
        ];
    }
}
