<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Class CreateOwnerToTeamMembersTable
 * creates table owner_to_team_members to resolve the problem of team member having multiple owners
 */
class CreateOwnerToTeamMembersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('owner_to_team_members', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('owner_id');
            $table->unsignedBigInteger('team_member_id');

            $table->foreign('owner_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('team_member_id')->references('id')->on('users')->cascadeOnDelete();

            $table->unique(['owner_id', 'team_member_id']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('owner_to_team_members');
    }
}
