<?php

namespace Database\Factories;

use App\FacebookGroups;
use App\GroupMembers;
use App\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Class GroupMemberFactory for create GroupMember data for testing purpose
 * @package Database\Factories
 */
class GroupMembersFactory extends Factory
{
    /**
     * @var string The name of the factory's corresponding model.
     */
    protected $model = GroupMembers::class;

    /**
     * Define the model's default state.
     *
     * @return array containing GroupMember {@see GroupMembers} data
     */
    public function definition()
    {
        $email = $this->faker->unique()->safeEmail;

        return [
            'a1' => $this->faker->text(50),
            'a2' => $email,
            'a3' => $this->faker->text(40),
            'date_add_time' => Carbon::now(),
            'email' => $email,
            'f_name' => $this->faker->firstName,
            'fb_id' => $this->faker->numberBetween(3123123, 43543534),
            'l_name' => $this->faker->lastName,
            'notes' => $this->faker->text(70),
            'respond_status' => GroupMembers::RESPONSE_STATUSES['NOT_ADDED'],
            'user_id' => User::factory()->create()->id,
            'img' => $this->faker->imageUrl(),
            'group_id' => FacebookGroups::factory()->create()->id,
        ];
    }
}
