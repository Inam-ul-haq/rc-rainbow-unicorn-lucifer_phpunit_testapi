<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

// @codingStandardsIgnoreLine
class CreateVoucherProductRestrictionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('voucher_product_restrictions', function (Blueprint $table) {
            $table->unsignedInteger('product_id');
            $table->unsignedInteger('voucher_id');

            $table->foreign('product_id')->references('id')->on('products'); ## FK20
            $table->foreign('voucher_id')->references('id')->on('vouchers'); ## FK21
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('voucher_product_restrictions');
    }
}
