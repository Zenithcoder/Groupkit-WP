<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Class AddColumnsWidthToGroupSettingsTable adds `columns_width` field to the `group_settings` table
 */
class AddColumnsWidthToGroupSettingsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('group_settings', function (Blueprint $table) {
            $table->json('columns_width')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('group_settings', function (Blueprint $table) {
            $table->dropColumn('columns_width');
        });
    }
}
