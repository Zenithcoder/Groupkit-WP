<?php

use Illuminate\Database\Migrations\Migration;

/**
 * Class ChangeStorageEngines
 * Changes storage engine to InnoDB. This allows us to add referential integrity.
 */
class ChangeStorageEngines extends Migration
{
    /**
     * Changes storage engine to InnoDB for the tables we need foreign key reference.
     * MyISAM, that was used before this migration, doesn't support referential integrity.
     *
     * @return void
     */
    public function up()
    {
        if (env('DB_CONNECTION') !== 'mysql') {
            // Apply this migration only for MySQL and MariaDB as they support InnoDB
            // `DB_CONNECTION` is set to mysql for MariaDB as well in laravel
            return;
        }
        DB::statement('ALTER TABLE users ENGINE = InnoDB');
        DB::statement('ALTER TABLE facebook_groups ENGINE = InnoDB');
    }

    /**
     * Reverts to MyISAM
     *
     * @return void
     */
    public function down()
    {
        if (env('DB_CONNECTION') !== 'mysql') {
            return;
        }
        DB::statement('ALTER TABLE users ENGINE = MyISAM');
        DB::statement('ALTER TABLE facebook_groups ENGINE = MyISAM');
    }
}
