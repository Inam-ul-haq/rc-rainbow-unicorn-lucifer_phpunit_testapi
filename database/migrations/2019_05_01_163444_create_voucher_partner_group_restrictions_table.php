<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

// @codingStandardsIgnoreLine
class CreateVoucherPartnerGroupRestrictionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('voucher_partner_group_restrictions', function (Blueprint $table) {
            $table->unsignedInteger('partner_group_id');
            $table->unsignedInteger('voucher_id');

            $table->foreign('partner_group_id')->references('id')->on('partner_groups'); ## FK33
            $table->foreign('voucher_id')->references('id')->on('vouchers'); ## FK24
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('voucher_partner_group_restrictions');
    }
}
