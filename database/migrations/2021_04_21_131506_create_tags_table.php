<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Class CreateTagsTable creates tags as a separate table for refactor tags in the `group_members` table
 */
class CreateTagsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('tags', function (Blueprint $table) {
            $table->id();
            $table->string('label')->charset('utf8')->collation('utf8_bin');
            $table->unsignedInteger('group_id');

            $table->foreign('group_id')->references('id')->on('facebook_groups')->cascadeOnDelete();
            $table->unique(['label', 'group_id']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('tags');
    }
}
