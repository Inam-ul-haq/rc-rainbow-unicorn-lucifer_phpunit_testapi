<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

// @codingStandardsIgnoreLine
class CreateVoucherReferrerRestrictionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('voucher_referrer_restrictions', function (Blueprint $table) {
            $table->unsignedInteger('voucher_id')->index();
            $table->unsignedInteger('referrer_id')->index();

            $table->foreign('voucher_id')->references('id')->on('vouchers');
            $table->foreign('referrer_id')->references('id')->on('referrers');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('voucher_referrer_restrictions');
    }
}
