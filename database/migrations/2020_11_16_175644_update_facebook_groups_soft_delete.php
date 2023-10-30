<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpdateFacebookGroupsSoftDelete extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        //add new column
        Schema::table('facebook_groups', function (Blueprint $table) {
            $table->softDeletes();
        });

        //migrate data
        DB::table('facebook_groups')
            ->where('is_deleted', 1)
            ->update(['deleted_at' => now()]);

        //delete old column
        Schema::table('facebook_groups', function (Blueprint $table) {
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
        Schema::table('facebook_groups', function (Blueprint $table) {
            $isDeletedColumn = $table->integer('is_deleted')->default(0);
            if (Schema::hasColumn('facebook_groups', 'user_id')) {
                $isDeletedColumn->after('user_id');
            }
        });

        //revert data migration
        DB::table('facebook_groups')
            ->whereNotNull('deleted_at')
            ->update(['is_deleted' => 1]);

        //add new column
        Schema::table('facebook_groups', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
}
