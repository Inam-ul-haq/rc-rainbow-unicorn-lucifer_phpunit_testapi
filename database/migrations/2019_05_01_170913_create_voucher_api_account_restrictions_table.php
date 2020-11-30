<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

// @codingStandardsIgnoreLine
class CreateVoucherApiAccountRestrictionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('voucher_api_account_restrictions', function (Blueprint $table) {
            $table->unsignedInteger('voucher_id');
            $table->unsignedInteger('user_id');

            $table->foreign('voucher_id')->references('id')->on('vouchers'); ## FK17
            $table->foreign('user_id')->references('id')->on('users'); ## FK18
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('voucher_api_account_restrictions');
    }
}
