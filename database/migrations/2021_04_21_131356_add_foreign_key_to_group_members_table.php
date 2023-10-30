<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Class AddForeignKeyToGroupMembersTable adds group_id as foreign key in the `group_members` table
 */
class AddForeignKeyToGroupMembersTable extends Migration
{
    /**
     * Adds `group_id` as foreign key
     *
     * @return void
     */
    public function up()
    {
        Schema::table('group_members', function (Blueprint $table) {
            $table->unsignedInteger('group_id')->change();
            $table->foreign('group_id')->references('id')->on('facebook_groups')->cascadeOnDelete();
        });
    }

    /**
     * Removes 'group_id' from foreign keys
     *
     * @return void
     */
    public function down()
    {
        Schema::table('group_members', function (Blueprint $table) {
            # Adds support for SQLite as test storage engine since SQLite doesn't have foreign keys
            if (env('DB_CONNECTION') === 'sqlite') {
                return;
            }
            $table->dropForeign(['group_id']);
        });
    }
}
