<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class ChangeStorageEnginesTable extends Migration
{
    /**
     * Run the migrations.
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

        $tables = [
            'auto_responder',
            'failed_jobs',
            'group_members',
            'migrations',
            'oauth_access_tokens',
            'oauth_auth_codes',
            'oauth_clients',
            'oauth_personal_access_clients',
            'oauth_refresh_tokens',
            'password_resets',
        ];

        foreach ($tables as $table) {
            DB::statement("ALTER TABLE {$table} ENGINE = InnoDB");
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        if (env('DB_CONNECTION') !== 'mysql') {
            return;
        }

        $tables = [
            'auto_responder',
            'failed_jobs',
            'group_members',
            'migrations',
            'oauth_access_tokens',
            'oauth_auth_codes',
            'oauth_clients',
            'oauth_personal_access_clients',
            'oauth_refresh_tokens',
            'password_resets',
        ];

        foreach ($tables as $table) {
            DB::statement("ALTER TABLE {$table} ENGINE = MyISAM");
        }
    }
}
