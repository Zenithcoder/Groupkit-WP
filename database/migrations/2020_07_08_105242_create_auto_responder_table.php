<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAutoResponderTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('auto_responder', function (Blueprint $table) {
            $table->increments('id');
            $table->string('responder_type', 100);
            $table->text('responder_json');
            $table->unsignedInteger('user_id');
            $table->unsignedInteger('group_id');
            $table->integer('is_check');
            $table->integer('is_deleted')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('auto_responder');
    }
}
