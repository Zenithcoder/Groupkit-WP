<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Class CreateGroupkitMailingListCredentialsTable for storing mailing list oAuth credentials
 */
class CreateGroupkitMailingListCredentialsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('groupkit_mailing_list_credentials', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('account_id');
            $table->string('client_id');
            $table->string('access_token');
            $table->string('refresh_token');
            $table->timestamp('expires_at');
            $table->timestamp('created_at');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('groupkit_mailing_list_credentials');
    }
}
