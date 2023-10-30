<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Class AddIndexToGroupMembersTable
 *
 * Add  index to `email` field in the `group_members` table
 */
class AddIndexToGroupMembersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('group_members', function (Blueprint $table) {
            $table->index('email');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('group_members', function (Blueprint $table) {
            # Adds support for SQLite as test storage engine since SQLite doesn't have foreign keys
            if (env('DB_CONNECTION') !== 'mysql') {
                return;
            }

            $table->dropIndex('email');
        });
    }
}
