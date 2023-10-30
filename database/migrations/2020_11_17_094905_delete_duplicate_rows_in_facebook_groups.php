<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class DeleteDuplicateRowsInFacebookGroups extends Migration
{
    /**
     * Removes duplicates in `facebook_groups` and leaves the latest entry where duplicates exist.
     * Creates compound key out of `fb_id` and `user_id` columns
     *
     * @return void
     */
    public function up()
    {
        Schema::table('facebook_groups', function (Blueprint $table) {
            $duplicateGroups = DB::table('facebook_groups')
                ->select(DB::raw('COUNT(*) AS copy_count, MAX(id) AS latest_duplicated_group_id, fb_id, user_id'))
                ->groupBy('fb_id', 'user_id')
                ->having('copy_count', '>', 1)->get();

            foreach ($duplicateGroups as $primaryKeys) {
                DB::table('facebook_groups')
                    ->where('fb_id', $primaryKeys->fb_id)
                    ->where('user_id', $primaryKeys->user_id)
                    ->where('id', '!=', $primaryKeys->latest_duplicated_group_id)
                    ->delete();
            }

            // Create compound key derived from fb_id and user_id
            $table->unique(['fb_id', 'user_id']);
        });

    }

    /**
     * Removes previously created compound unique key constraint.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('facebook_groups', function (Blueprint $table) {
            $table->dropUnique(['fb_id', 'user_id']);
        });
    }
}
