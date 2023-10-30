<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePaymentsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->increments('id');
            $table->string('txn_id')->nullable();
            $table->float('payment_gross', 10, 10)->nullable();
            $table->string('currency_code', 10)->nullable();
            $table->string('payer_email')->nullable();
            $table->string('receiver_email')->nullable();
            $table->timestamp('payment_date', 0);
            $table->string('payment_status', 20)->nullable();
            $table->unsignedInteger('user_id')->nullable();
            $table->string('subscriptions', 100)->nullable();
            $table->integer('is_deleted')->default(0);
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
        Schema::dropIfExists('payments');
    }
}
