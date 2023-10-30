<?php

use App\OwnerToTeamMember;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Class ConnectOwnerToTeamMembersTableWithTeamMemberAccessTable
 * connects pivot tables 'owner_to_team_members' and 'team_member_access' to set up a reference between these tables
 */
class ConnectOwnerToTeamMembersTableWithTeamMemberGroupAccessTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $teamAssignments = OwnerToTeamMember::with('owner', 'owner.groupsOwned')->get();

        foreach ($teamAssignments as $teamAssignment) {
            foreach ($teamAssignment->owner->groupsOwned as $group) {
                $teamMemberGroupAccess = DB::table('team_member_group_access')
                    ->where('user_id', $teamAssignment->team_member_id)
                    ->where('facebook_group_id', $group->id);
                if ($teamMemberGroupAccess->first()) {
                    $teamMemberGroupAccess->update(['owner_to_team_member_id' => $teamAssignment->id]);
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::disableForeignKeyConstraints();
        DB::table('team_member_group_access')->update(['owner_to_team_member_id' => null]);
        Schema::enableForeignKeyConstraints();
    }
}
