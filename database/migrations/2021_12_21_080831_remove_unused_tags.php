<?php

use App\Tag;
use Illuminate\Database\Migrations\Migration;

/**
 * Removes unused tags from the 'tags' table
 */
class RemoveUnusedTags extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Tag::doesntHave('members')->delete();
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Because we don't have a reference for unused tags, we can't revert them
    }
}
