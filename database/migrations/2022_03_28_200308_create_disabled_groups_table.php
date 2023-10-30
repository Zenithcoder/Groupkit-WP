<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Class CreateDisabledGroupsTable
 *
 * Adds `disabled_groups` table which contains information about
 * the user and the numeric id of their disabled facebook group.
 */
class CreateDisabledGroupsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('disabled_groups', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id');
            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->cascadeOnDelete();

            $table->string('facebook_group_fb_id');
            $table->foreign('facebook_group_fb_id')
                ->references('fb_id')
                ->on('facebook_groups')
                ->cascadeOnDelete();

            $table->unique(['user_id', 'facebook_group_fb_id']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('disabled_groups');
    }
}
