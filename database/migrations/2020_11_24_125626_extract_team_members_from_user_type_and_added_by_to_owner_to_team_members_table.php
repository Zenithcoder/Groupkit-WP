<?php

use App\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Class ExtractTeamMembersFromUserTypeAndAddedByToOwnerToTeamMembersTable
 * imports all members (a.k.a. group moderators/sub-users/assistants) in owner_to_team_members
 * table according to added_by and user_id fields from users table
 */
class ExtractTeamMembersFromUserTypeAndAddedByToOwnerToTeamMembersTable extends Migration
{
    /**
     * Imports team members with the team owners in owner_to_team_members table
     * Removes `user_type` and `added_by` fields from users table
     *
     * @return void
     */
    public function up()
    {
        $teamMembers = User::withTrashed()->where('user_type', 3)->get()->map(function ($user) {
            return [
                'owner_id' => $user->added_by,
                'team_member_id' => $user->id,
            ];
        })->toArray();

        $teamMemberChunks = array_chunk($teamMembers, config('database.connections.mysql.chunk_size'));
        array_walk($teamMemberChunks, [DB::table('owner_to_team_members'), 'insert']);

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['user_type', 'added_by']);
        });
    }

    /**
     * Creates `user_type` and `added_by` field in users table
     * Import users from owner_to_team_members to users table
     *
     * @return void
     */
    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->integer('user_type')->nullable()->comment('1-Admin,2-User,3-SubUser');
            $table->integer('added_by')->nullable();
        });

        $users = DB::table('owner_to_team_members')->get(['owner_id', 'team_member_id']);
        foreach ($users as $user) {
            User::withTrashed()->find($user->team_member_id)->update([
                'user_type' => 3, #rollback team members to sub users
                'added_by' => $user->owner_id,
            ]);
        }

        Schema::disableForeignKeyConstraints();
        DB::table('owner_to_team_members')->truncate();
        Schema::enableForeignKeyConstraints();
    }
}
