<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpdateGroupMembersSoftDelete extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        //add new column
        Schema::table('group_members', function (Blueprint $table) {
            $table->softDeletes()->after('updated_at');
        });

        //migrate data
        DB::table('group_members')
            ->where('is_deleted', 1)
            ->update(['deleted_at' => now()]);

        //delete old column
        Schema::table('group_members', function (Blueprint $table) {
            $table->dropColumn('is_deleted');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //restore old column
        Schema::table('group_members', function (Blueprint $table) {
            $isDeletedColumn = $table->integer('is_deleted')->default(0);

            if (Schema::hasColumn('group_members', 'group_id')) {
                $isDeletedColumn->after('group_id');
            }
        });

        //revert data migration
        DB::table('group_members')
            ->whereNotNull('deleted_at')
            ->update(['is_deleted' => 1]);

        //add new column
        Schema::table('group_members', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
}
