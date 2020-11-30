<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

// @codingStandardsIgnoreLine
class CreateReferrerPointsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('referrer_points', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('referrer_id');
            $table->unsignedInteger('coupon_id')->nullable();
            $table->unsignedInteger('consumer_id')->nullable();
            $table->datetime('transaction_date');
            $table->enum('transaction_type', ['add', 'remove']);
            $table->string('notes')->nullable();
            $table->integer('points');

            $table->foreign('referrer_id')->references('id')->on('referrers');
            $table->foreign('coupon_id')->references('id')->on('coupons');
            $table->foreign('consumer_id')->references('id')->on('consumers');

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
        Schema::dropIfExists('referrer_points');
    }
}
