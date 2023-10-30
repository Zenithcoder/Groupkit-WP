<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Class DropFbGroupIdForeignKeyInDisabledGroupsTable
 *
 * Removes foreign index from the `facebook_group_fb_id` in the `disabled_groups` table
 */
class DropFbGroupIdForeignKeyInDisabledGroupsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('disabled_groups', function (Blueprint $table) {
            # Adds support for SQLite as test storage engine since SQLite doesn't have foreign keys
            if (env('DB_CONNECTION') !== 'mysql') {
                return;
            }

            $table->dropForeign(['facebook_group_fb_id']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('disabled_groups', function (Blueprint $table) {
            # Adds support for SQLite as test storage engine since SQLite doesn't have foreign keys
            if (env('DB_CONNECTION') !== 'mysql') {
                return;
            }

            $table->foreign('facebook_group_fb_id')
                ->references('fb_id')
                ->on('facebook_groups')
                ->cascadeOnDelete();
        });
    }
}
