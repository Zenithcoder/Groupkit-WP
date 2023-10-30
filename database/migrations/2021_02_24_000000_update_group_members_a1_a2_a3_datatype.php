<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpdateGroupMembersA1A2A3Datatype extends Migration
{

    /**
     * Updates answers fields type to text type
     *
     * @return void
     */
    public function up()
    {
        Schema::table('group_members', function (Blueprint $table) {
            $table->text('a1')->change();
            $table->text('a2')->change();
            $table->text('a3')->change();
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
            $table->string('a1')->nullable()->change();
            $table->string('a2')->nullable()->change();
            $table->string('a3')->nullable()->change();
        });
    }
}
