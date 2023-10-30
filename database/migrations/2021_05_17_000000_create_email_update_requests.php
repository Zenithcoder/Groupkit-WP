<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Class CreateEmailUpdateRequests adds `email_update_requests` table for maintaining users change email requests.
 */
class CreateEmailUpdateRequests extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('email_update_requests', function (Blueprint $table) {
            $table->increments('id');
            $table->string('current_email');
            $table->string('new_email');
            $table->string('activation_code', 255);
            $table->timestamp('expires_at')->nullable();
            $table->string('ip_address')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('email_update_requests');
    }
}
