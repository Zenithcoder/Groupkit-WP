<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Class ExtractFacebookGroupIdsFromUsers
 * Enters facebook group Ids to a separate table instead of keeping them in comma separated values in `users` table
 */
class ExtractFacebookGroupIdsFromUsers extends Migration
{
    /**
     * Extracts all values from `users`.`facebook_groups_id` and stores them in `team_member_group_access`.
     * Removes `facebook_groups_id` column from `users` after extraction.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            $users = DB::table('users')
                ->select('id', 'facebook_groups_id')
                ->whereNotNull('facebook_groups_id')->get();

            foreach ($users as $user) {
                $groupIds = explode(',', $user->facebook_groups_id);
                foreach ($groupIds as $groupId) {
                    try {
                        DB::table('team_member_group_access')->insert([
                            'user_id' => $user->id,
                            'facebook_group_id' => $groupId,
                        ]);
                    } catch (\Exception $e) {
                        // We might end up having an id from $groupIds that does not actually exist in `facebook_groups`.
                        // In this case we get foreign key constraint error and code will stop execution. That's why we
                        // add try/catch block here so when that happens, code just continues with next iteration.
                    }
                }
            }

            $table->dropColumn('facebook_groups_id');
        });
    }

    /**
     * Takes entries from `team_member_group_access` and adds them back to `users` table in `facebook_groups_id`.
     * Truncates `team_member_group_access` table after adding it back to `users` table
     *
     * @return void
     */
    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->longText('facebook_groups_id')->nullable()->after('trial_ends_at');
        });

        $users = DB::table('team_member_group_access')
            ->select('user_id', 'facebook_group_id')
            ->get()
            ->groupBy('user_id');

        foreach ($users as $userId => $teamGroupAccessData) {
            DB::table('users')
                ->where('id', $userId)
                ->update(['facebook_groups_id' =>
                    implode(',', $teamGroupAccessData->pluck('facebook_group_id')->all())]);
        }

        DB::table('team_member_group_access')->truncate();
    }
}
