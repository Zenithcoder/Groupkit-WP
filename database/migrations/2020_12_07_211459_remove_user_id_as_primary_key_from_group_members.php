<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Class RemoveUserIdAsPrimaryKeyFromGroupMembers and sets it as merely a foreign key
 */
class RemoveUserIdAsPrimaryKeyFromGroupMembers extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('group_members', function (Blueprint $table) {
            /////////////////////////////////////////////////////////////////////////
            /// Remove duplicated Group Member entries from previous erroneous code
            /////////////////////////////////////////////////////////////////////////
            $duplicateMembers = DB::table('group_members')
                ->select(DB::raw('COUNT(*) AS copy_count, MAX(id) AS current_entry, fb_id, group_id'))
                ->groupBy('fb_id', 'group_id')
                ->having('copy_count', '>', 1)->get();

            foreach ($duplicateMembers as $primaryKeys) {
                DB::table('group_members')
                    ->where('fb_id', $primaryKeys->fb_id)
                    ->where('group_id', $primaryKeys->group_id)
                    ->whereNotIn('id', [$primaryKeys->current_entry])
                    ->delete();
            }
            /////////////////////////////////////////////////////////////////////////

            /////////////////////////////////////////////////////////////////////////
            /// Remove current composite unique constraint
            /////////////////////////////////////////////////////////////////////////
            $table->dropUnique(['fb_id', 'group_id', 'user_id']);

            /////////////////////////////////////////////////////////////////////////
            /// Set new compound composite key to allow for "upsert"
            /////////////////////////////////////////////////////////////////////////
            $table->unique(['fb_id', 'group_id']);
            /////////////////////////////////////////////////////////////////////////
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
            /////////////////////////////////////////////////////////////////////////
            /// Remove current composite unique constraint
            /////////////////////////////////////////////////////////////////////////
            $table->dropUnique(['fb_id', 'group_id']);

            /////////////////////////////////////////////////////////////////////////
            /// Set new compound composite key to allow for "upsert"
            /////////////////////////////////////////////////////////////////////////
            $table->unique(['fb_id', 'group_id', 'user_id']);
            /////////////////////////////////////////////////////////////////////////
        });
    }
}
