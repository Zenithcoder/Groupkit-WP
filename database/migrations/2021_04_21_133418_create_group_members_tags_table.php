<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Class CreateGroupMembersTagsTable adds relation between `tags` and `group_members` tables
 */
class CreateGroupMembersTagsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('group_members_tags', function (Blueprint $table) {
            $table->unsignedInteger('group_member_id');
            $table->unsignedBigInteger('tag_id');
            $table->unsignedInteger('group_id');

            # Unique primary key combination of `group_member_id` and `tag_id` fields
            $table->primary(['group_member_id', 'tag_id']);

            $table->foreign('group_member_id')->references('id')->on('group_members')->cascadeOnDelete();
            $table->foreign('tag_id')->references('id')->on('tags')->cascadeOnDelete();
            $table->foreign('group_id')->references('id')->on('facebook_groups')->cascadeOnDelete();

            $table->timestamp('created_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('group_members_tags');
    }
}
