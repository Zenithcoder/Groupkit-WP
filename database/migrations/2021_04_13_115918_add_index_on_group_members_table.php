<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Class AddIndexOnGroupMembersTable optimizes `group_members` table
 */
class AddIndexOnGroupMembersTable extends Migration
{
    /**
     * Adds index on the group_id field
     *
     * @return void
     */
    public function up()
    {
        Schema::table('group_members', function (Blueprint $table) {
            $table->index('group_id');
        });
    }

    /**
     * Removes index from the group_id field
     *
     * @return void
     */
    public function down()
    {
        Schema::table('group_members', function (Blueprint $table) {
            $table->dropIndex(['group_id']);
        });
    }
}
