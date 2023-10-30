<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateRefundPaymentTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('refund_payment', function (Blueprint $table) {
            $table->increments('id');
            $table->string('refund_pay_id');
            $table->text('refund_object');
            $table->string('refund_amount');
            $table->string('refund_balance_transaction');
            $table->string('refund_charge');
            $table->string('refund_currency', 10);
            $table->string('receipt_number');
            $table->text('reason');
            $table->string('status', 20);
            $table->unsignedInteger('user_id');
            $table->string('subscriptions', 100);
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
        Schema::dropIfExists('refund_payment');
    }
}
