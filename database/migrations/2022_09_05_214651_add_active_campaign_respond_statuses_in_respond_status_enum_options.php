<?php

use Illuminate\Database\Migrations\Migration;

/**
 * Class AddActiveCampaignRespondStatusesInRespondStatusEnumOptions adds Active Campaign response statuses
 * in the `respond_status` enum field of the `group_members` table
 */
class AddActiveCampaignRespondStatusesInRespondStatusEnumOptions extends Migration
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
                       ENUM(
                        'No Email',
                        'Added',
                        'Not Added',
                        'Error',
                        'Column Limit Exceeded',
                        'Request Could Not Be Processed Due To Payment Issues',
                        'Request Could Not Be Processed Due To Authorization Or Authentication Issue',
                        'The Contact Does Not Exist In The ActiveCampaign System',
                        'The Contact Data Was Invalid To Be Added Or Updated To The ActiveCampaign System'
                        )");
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
                       ENUM('No Email', 'Added', 'Not Added', 'Error', 'Column Limit Exceeded')");
    }
}
