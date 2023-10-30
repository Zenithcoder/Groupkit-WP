<?php

namespace Database\Factories;

use App\GroupkitMailingListCredential;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Class GroupkitMailingListCredentialFactory creates mailing list credential in the database for testing purpose
 *
 * @package Database\Factories
 */
class GroupkitMailingListCredentialFactory extends Factory
{
    /**
     * Mailing list credential expiration time in hours
     *
     * @var int
     */
    public const EXPIRATION_TIME = 24;

    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = GroupkitMailingListCredential::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition(): array
    {
        return [
            'account_id' => rand(),
            'client_id' => uniqid(),
            'access_token' => Hash::make(Str::random(10)),
            'refresh_token' => Hash::make(Str::random(10)),
            'expires_at' => now()->addHours(self::EXPIRATION_TIME),
            'created_at' => now(),
        ];
    }
}
