<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Class CreateTeamMemberGroupAccess
 * Adds new `team_member_group_access` table which contains information about each user that has access to specific group
 * (Note: A row in this table means that user has access to see but not to manage the group - he's only a team member)
 */
class CreateTeamMemberGroupAccess extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('team_member_group_access', function (Blueprint $table) {

            $table->increments('id');

            $table->bigInteger('user_id')->unsigned();
            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->onDelete('cascade');

            $table->integer('facebook_group_id')->unsigned();
            $table->foreign('facebook_group_id')
                ->references('id')
                ->on('facebook_groups')
                ->onDelete('cascade');

            $table->unique(['user_id', 'facebook_group_id']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('team_member_group_access');
    }
}
