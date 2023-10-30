<?php

namespace Database\Factories;

use App\EmailUpdateRequest;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Crypt;

/**
 * Class EmailUpdateRequestFactory for create EmailUpdateRequest for testing purpose
 * @package Database\Factories
 */
class EmailUpdateRequestFactory extends Factory
{
    /** @var int represents verification emails expiration time in hours */
    public const EXPIRATION_TIME = 24;

    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = EmailUpdateRequest::class;

    /**
     * Define the model's default state.
     *
     * @return array containing AutoResponder {@see EmailUpdateRequest} data
     */
    public function definition()
    {
        return [
            'current_email' => $this->faker->unique()->safeEmail,
            'new_email' => $this->faker->unique()->safeEmail,
            'activation_code' => Crypt::encryptString($this->faker->unique()->safeEmail),
            'ip_address' => $this->faker->unique()->localIpv4,
            'expires_at' => now()->addHours(self::EXPIRATION_TIME),
        ];
    }
}
