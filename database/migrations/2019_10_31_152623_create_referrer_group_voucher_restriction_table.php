<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

// @codingStandardsIgnoreLine
class CreateReferrerGroupVoucherRestrictionTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('referrer_group_voucher_restriction', function (Blueprint $table) {
            $table->unsignedBigInteger('referrer_group_id')->index();
            $table->unsignedInteger('voucher_id')->index();

            $table->foreign('referrer_group_id')->references('id')->on('referrer_groups');
            $table->foreign('voucher_id')->references('id')->on('vouchers');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('referrer_group_voucher_restriction');
    }
}
