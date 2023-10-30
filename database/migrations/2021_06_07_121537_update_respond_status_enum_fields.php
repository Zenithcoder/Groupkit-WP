<?php

use Illuminate\Database\Migrations\Migration;

/**
 * Class UpdateRespondStatusToVarchar adds new enum option in the `respond_status` field of the `group_members` table
 */
class UpdateRespondStatusEnumFields extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        # Adds support for SQLite as test storage engine since SQLite doesn't have change function
        if (env('DB_CONNECTION') === 'sqlite') {
            return;
        }

        DB::statement("ALTER TABLE `group_members`
                       CHANGE respond_status respond_status
                       ENUM('No Email', 'Added', 'Not Added', 'Error', 'Column Limit Exceeded')");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        # Adds support for SQLite as test storage engine since SQLite doesn't have change function
        if (env('DB_CONNECTION') === 'sqlite') {
            return;
        }

        DB::statement("ALTER TABLE `group_members`
                       CHANGE respond_status respond_status
                       ENUM('No Email', 'Added', 'Not Added', 'Error')");
    }
}
