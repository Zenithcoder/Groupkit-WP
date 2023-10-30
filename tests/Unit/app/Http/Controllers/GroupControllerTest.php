<?php

namespace Tests\Unit\app\Http\Controllers;

use App\Http\Controllers\GroupController;
use App\TeamMemberGroupAccess;
use App\User;
use App\FacebookGroups;
use App\OwnerToTeamMember;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

/**
 * Class GroupControllerTest adds test coverage for {@see GroupController}
 *
 * @package Tests\Unit\app\Http\Controllers
 * @coversDefaultClass \App\Http\Controllers\GroupController
 */
class GroupControllerTest extends TestCase
{
    use DatabaseMigrations;
    use DatabaseTransactions;

    /**
     * @var User contains newly created user object
     */
    private User $user;

    /**
     * Setup test dependencies
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->user = $this->actingAsUser();
    }

    /**
     * @test
     * that show returns {@see Response::HTTP_OK} response when group owner try to get his owned group
     *
     * @covers ::show
     */
    public function show_withGroupAccessAsOwner_returnsResponseSuccess()
    {
        $facebookGroup = FacebookGroups::factory()->create(['user_id' => $this->user->id]);

        $response = $this->get('groups/' . $facebookGroup->id);

        $this->assertAuthenticatedAs($this->user);
        $response->assertOk();
        $response->assertViewIs('home');
    }

    /**
     * @test
     * that show returns {@see Response::HTTP_UNAUTHORIZED} response when group owner try to access group that not own
     *
     * @covers ::show
     */
    public function show_withoutGroupAccessAsOwner_returnsResponseUnauthorized()
    {
        FacebookGroups::factory()->create(['id' => 1]);

        $response = $this->get('groups/5');

        $this->assertAuthenticatedAs($this->user);
        $response->assertUnauthorized();
        $response->assertSee('Unauthorized');
    }

    /**
     * @test
     * that show returns {@see Response::HTTP_OK} response when team member try to get group that has access
     *
     * @covers ::show
     */
    public function show_withGroupAccessAsTeamMember_returnsResponseSuccess()
    {
        list($teamMember, $facebookGroup) = $this->showAsTeamMemberSetUp();

        $response = $this->get('groups/' . $facebookGroup->id);

        $response->assertOk();
        $response->assertViewIs('home');
        $this->assertAuthenticatedAs($teamMember);
    }

    /**
     * @test
     * that show returns {@see Response::HTTP_UNAUTHORIZED} response
     * when team member try to get group that has not an access
     *
     * @covers ::show
     */
    public function show_withoutGroupAccessAsTeamMember_returnsUnauthorizedResponse()
    {
        list($teamMember) = $this->showAsTeamMemberSetUp();

        $response = $this->get('groups/5');

        $this->assertAuthenticatedAs($teamMember);
        $response->assertUnauthorized();
        $response->assertSee('Unauthorized');
    }

    /**
     * Setup method for show test case with team member
     *
     * @return array[] containing {@see User} as team member and created {@see FacebookGroups} for that team member
     */
    public function showAsTeamMemberSetUp()
    {
        $facebookGroup = FacebookGroups::factory()->create(['id' => 1, 'user_id' => $this->user->id]);
        $teamMember = User::factory()->create();
        $this->actingAs($teamMember);
        $ownerToTeamMembersData = [
            'team_member_id' => $teamMember->id,
            'owner_id' => $this->user->id,
        ];
        $ownerToTeamMember = OwnerToTeamMember::factory()->create($ownerToTeamMembersData);
        TeamMemberGroupAccess::factory()->create([
            'user_id' => $teamMember->id,
            'facebook_group_id' => $facebookGroup->id,
            'owner_to_team_member_id' => $ownerToTeamMember->id,
        ]);

        return [$teamMember, $facebookGroup];
    }
}
