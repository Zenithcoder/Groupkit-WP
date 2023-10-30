<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Class AddQuestionOneTwoThreeToFacebookGroupsTable adds `questionOne`, `questionTwo`,
 * and `questionThree` fields to the `facebook_groups` table.
 */
class AddQuestionOneTwoThreeToFacebookGroupsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('facebook_groups', function (Blueprint $table) {
            $table->string('questionThree')->nullable()->after('user_id');
            $table->string('questionTwo')->nullable()->after('user_id');
            $table->string('questionOne')->nullable()->after('user_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        /**
         * Do not apply this migration for SQLite engine because tests run on it
         * Tests can't be executed if `questionOne`,`questionTwo`,`questionThree` field is dropped
         */
         if (env('DB_CONNECTION') === 'sqlite') {
            return;
        }

        Schema::table('facebook_groups', function (Blueprint $table) {
            $table->dropColumn(['questionOne', 'questionTwo', 'questionThree']);
        });
    }
}
