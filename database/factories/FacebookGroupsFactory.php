<?php

namespace Database\Factories;

use App\FacebookGroups;
use App\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Class FacebookGroupsFactory for create Facebook groups for testing purpose
 * @package Database\Factories
 */
class FacebookGroupsFactory extends Factory
{
    /**
     * @var string The name of the factory's corresponding model.
     */
    protected $model = FacebookGroups::class;

    /**
     * Define the model's default state.
     *
     * @return array containing FacebookGroups {@see FacebookGroups} data
     */
    public function definition()
    {
        return [
            'fb_id' => $this->faker->numberBetween(321321, 4353453),
            'fb_name' => $this->faker->unique()->name,
            'user_id' => User::factory()->create()->id,
        ];
    }
}
