<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Class ChangeNullablePropertyForPasswordField sets nullable for password field in the `users` table
 */
class ChangeNullablePropertyForPasswordField extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (env('DB_CONNECTION') !== 'sqlite') {
            /**
             * Apply this migration only for SQLite engine because tests run on it
             * Tests can't be executed if password field is not nullable
             */
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->string('password')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        if (env('DB_CONNECTION') !== 'sqlite') {
            /**
             * Apply this migration only for SQLite engine because tests run on it
             * Tests can't be executed if password field is not nullable
             */
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->string('password')->change();
        });
    }
}
