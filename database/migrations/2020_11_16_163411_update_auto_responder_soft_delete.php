<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpdateAutoResponderSoftDelete extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        //add new column
        Schema::table('auto_responder', function (Blueprint $table) {
            $table->softDeletes();
        });

        //migrate data
        DB::table('auto_responder')
            ->where('is_deleted', 1)
            ->update(['deleted_at' => now()]);

        //delete old column
        Schema::table('auto_responder', function (Blueprint $table) {
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
        Schema::table('auto_responder', function (Blueprint $table) {
            /** @see database/migrations/2020_07_08_105242_create_auto_responder_table.php:23 */
            $isDeletedColumn = $table->integer('is_deleted')->default(0);
            if (Schema::hasColumn('auto_responder', 'is_check')) {
                $isDeletedColumn->after('is_check');
            }
        });

        //revert data migration
        DB::table('auto_responder')
            ->whereNotNull('deleted_at')
            ->update(['is_deleted' => 1]);

        //add new column
        Schema::table('auto_responder', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
}
