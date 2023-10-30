<?php

namespace Database\Factories;

use App\FacebookGroups;
use App\OwnerToTeamMember;
use App\TeamMemberGroupAccess;
use App\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Class TeamMemberGroupAccessFactory for create TeamMemberGroupAccess for testing purpose
 * @package Database\Factories
 */
class TeamMemberGroupAccessFactory extends Factory
{
    /**
     * @var string The name of the factory's corresponding model.
     */
    protected $model = TeamMemberGroupAccess::class;

    /**
     * Define the model's default state.
     *
     * @return array containing TeamMemberGroupAccessFactory {@see TeamMemberGroupAccess} data
     */
    public function definition(): array
    {
        $teamMember = User::factory()->create();

        return [
            'user_id'                 => $teamMember->id,
            'facebook_group_id'       => FacebookGroups::factory()->create()->id,
            'owner_to_team_member_id' => OwnerToTeamMember::factory()->create([
                'team_member_id' => $teamMember->id,
            ])->id,
        ];
    }
}
