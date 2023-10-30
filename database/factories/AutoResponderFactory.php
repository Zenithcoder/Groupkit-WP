<?php

namespace Database\Factories;

use App\AutoResponder;
use App\FacebookGroups;
use App\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Class AutoResponderFactory for create AutoResponder for testing purpose
 * @package Database\Factories
 */
class AutoResponderFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = AutoResponder::class;

    /**
     * Define the model's default state.
     *
     * @return array containing AutoResponder {@see AutoResponder} data
     */
    public function definition()
    {
        $user = User::factory()->create();

        return [
            'responder_type' => 'ConvertKit',
            'user_id' => $user->id,
            'group_id' => FacebookGroups::factory()->create(['user_id' => $user->id])->id,
            'is_check' => 1,
            'responder_json' => json_encode([
                'activeList' => [
                    'label' => 'FB Group',
                    'value' => $this->faker->randomNumber(),
                ],
                'api_key' => $this->faker->word,
            ]),
        ];
    }
}
