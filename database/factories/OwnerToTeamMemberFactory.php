<?php

namespace Database\Factories;

use App\OwnerToTeamMember;
use App\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Class OwnerToTeamMemberFactory for create OwnerToTeamMember for testing purpose
 * @package Database\Factories
 */
class OwnerToTeamMemberFactory extends Factory
{
    /**
     * @var string The name of the factory's corresponding model.
     */
    protected $model = OwnerToTeamMember::class;

    /**
     * Define the model's default state.
     *
     * @return array containing OwnerToTeamMember {@see OwnerToTeamMember} data
     */
    public function definition(): array
    {
        return [
            'owner_id' => User::factory()->create()->id,
            'team_member_id' => User::factory()->create()->id,
        ];
    }
}
