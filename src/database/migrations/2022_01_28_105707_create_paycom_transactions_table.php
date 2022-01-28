<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePaycomTransactionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('paycom_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('paycom_transaction_id',25);
            $table->string('paycom_time',13);
            $table->string('paycom_time_datetime');
            $table->dateTime('create_time');
            $table->dateTime('perform_time');
            $table->dateTime('cancel_time');
            $table->bigInteger('amount',20);
            $table->integer('state',11);
            $table->integer('reason',);
            $table->string('receivers',500);
            $table->unsignedBigInteger('booking_id');

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
        Schema::dropIfExists('paycom_transactions');
    }
}
