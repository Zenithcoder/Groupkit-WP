<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Class UpdateUsersSoftDelete
 * Adds laravel's way of soft deleting in our existing `users` table (`deleted_at` column)
 * and deletes custom column `is_deleted`
 */
class UpdateUsersSoftDelete extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        //add new column
        Schema::table('users', function (Blueprint $table) {
            $table->softDeletes()->after('updated_at');
        });

        //migrate data
        DB::table('users')
            ->where('is_deleted', 1)
            ->update(['deleted_at' => now()]);

        //delete old column
        Schema::table('users', function (Blueprint $table) {
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
        Schema::table('users', function (Blueprint $table) {
            $isDeletedColumn = $table->integer('is_deleted')->default(0);
            //return the column to the original position if possible
            if (Schema::hasColumn('users', 'added_by')) {
                $isDeletedColumn->after('added_by');
            }
        });

        //revert data migration
        DB::table('users')
            ->whereNotNull('deleted_at')
            ->update(['is_deleted' => 1]);

        //add new column
        Schema::table('users', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
}
