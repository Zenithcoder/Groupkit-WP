<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Class RemoveAllGroupMembersWithoutGroup removes all group members which groups do not exist in the database
 */
class RemoveAllGroupMembersWithoutGroup extends Migration
{
    /**
     * Deletes all group members without existing facebook group
     *
     * @return void
     */
    public function up()
    {
        DB::select("DELETE FROM group_members WHERE group_id NOT IN (SELECT id FROM facebook_groups)");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // We can't restore deleted group members from the database since we don't have their data
    }
}
