<?php

namespace Database\Factories;

use App\FacebookGroups;
use App\Tag;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Class TagFactory creates tag in the database for testing purpose
 *
 * @package Database\Factories
 */
class TagFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Tag::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [
            'label' => $this->faker->name,
            'group_id' => FacebookGroups::factory()->create()->id,
        ];
    }
}
