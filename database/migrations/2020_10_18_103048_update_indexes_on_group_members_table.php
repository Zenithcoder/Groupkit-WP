<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Class UpdateIndexesOnGroupMembersTable
 *
 * Create indexes on the Group Members as a compound primary key
 */
class UpdateIndexesOnGroupMembersTable extends Migration
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
                ->select(DB::raw('COUNT(*) AS copy_count, MAX(id) AS current_entry, fb_id, group_id, user_id'))
                ->groupBy('fb_id', 'group_id', 'user_id')
                ->having('copy_count', '>', 1)->get();

            foreach ($duplicateMembers as $primaryKeys) {
                DB::table('group_members')
                    ->where('fb_id', $primaryKeys->fb_id)
                    ->where('group_id', $primaryKeys->group_id)
                    ->where('user_id', $primaryKeys->user_id)
                    ->whereNotIn('id', [$primaryKeys->current_entry])
                    ->delete();
            }
            /////////////////////////////////////////////////////////////////////////

            /////////////////////////////////////////////////////////////////////////
            /// Set new compound key to allow for "upsert"
            /////////////////////////////////////////////////////////////////////////
            $table->unsignedBigInteger('group_id')->change();
            $table->unique(['fb_id', 'group_id', 'user_id']);
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
            /// Remove composite unique constraint
            /////////////////////////////////////////////////////////////////////////
            $table->dropUnique(['fb_id', 'group_id', 'user_id']);
        });
    }
}
