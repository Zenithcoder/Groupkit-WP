<?php

use Illuminate\Database\Migrations\Migration;

/**
 * Class UpdateStripeAccountEnumValues adds `new` as additional value in the `stripe_account` enum field
 */
class UpdateStripeAccountEnumValues extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        # Adds support for SQLite as test storage engine since SQLite doesn't have change function
        if (env('DB_CONNECTION') === 'sqlite') {
            return;
        }

        DB::statement("ALTER TABLE `users`
                       CHANGE stripe_account stripe_account
                       ENUM('legacy', 'default', 'new') DEFAULT 'default'");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
     # Adds support for SQLite as test storage engine since SQLite doesn't have change function
        if (env('DB_CONNECTION') === 'sqlite') {
            return;
        }

        DB::statement("ALTER TABLE `users`
                       CHANGE stripe_account stripe_account
                       ENUM('legacy', 'default') DEFAULT 'default'");
    }
}
