<?php

use App\User;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

/**
 * Create a new table 'group_settings' that is intended to
 * store the configurations for a user's Facebook group. For now, it stores
 * information about columns visibility on the UI, that can be different
 * for every user that has access to the app. So it stores the data
 * about columns visibility as well as a user ID and a group ID (FB group ID).
 */
class CreateTableGroupSettings extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('group_settings', function (Blueprint $table) {
            $table->foreignIdFor(User::class, 'user_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->unsignedInteger('group_id');
            $table->foreign('group_id')
                ->references('id')
                ->on('facebook_groups')
                ->onDelete('cascade');

            $table->json('columns_visibility');

            $table->primary(['user_id', 'group_id']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('group_settings');
    }
}
