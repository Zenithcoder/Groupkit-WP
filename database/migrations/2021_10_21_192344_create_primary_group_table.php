<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Class CreatePrimaryGroupTable
 * Adds new `primary_group` table which contains information about users selected primary group detail
 * before downgrading there plan and associated job id which is related to jobs table
 */
class CreatePrimaryGroupTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('primary_group', function (Blueprint $table) {
            $table->increments('id');

            $table->bigInteger('user_id')->unsigned();
            $table->foreign('user_id')
                ->references('id')
                ->on('users');

            $table->unsignedInteger('facebook_group_id');
            $table->foreign('facebook_group_id')
                ->references('id')
                ->on('facebook_groups');

            $table->bigInteger('job_id')->unsigned();
            $table->foreign('job_id')
                ->references('id')
                ->on('jobs')
                ->onDelete('cascade');

            $table->timestamps();

            $table->softDeletes();

            $table->unique(['user_id', 'facebook_group_id', 'job_id']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('primary_group');
    }
}