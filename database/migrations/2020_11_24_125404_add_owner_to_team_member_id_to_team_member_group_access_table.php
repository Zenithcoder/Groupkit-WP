<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Class AddOwnerToTeamMemberIdToTeamMemberAccessTable
 * adds owner_to_team_member_id foreign key to team_member_access table
 * to accomplish referential integrity between these tables
 */
class AddOwnerToTeamMemberIdToTeamMemberGroupAccessTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('team_member_group_access', function (Blueprint $table) {
            $table->unsignedBigInteger('owner_to_team_member_id')->nullable();
            $table->foreign('owner_to_team_member_id')
                ->references('id')
                ->on('owner_to_team_members')
                ->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        if (Schema::hasColumn('team_member_group_access', 'owner_to_team_member_id')) {
            Schema::table('team_member_group_access', function (Blueprint $table) {
                # Adds support for SQLite as test storage engine since SQLite doesn't have foreign keys
                if (env('DB_CONNECTION') !== 'mysql') {
                    $table->dropColumn(['owner_to_team_member_id']);
                    return;
                }
                $table->dropForeign(['owner_to_team_member_id']);
            });
        }
    }
}
