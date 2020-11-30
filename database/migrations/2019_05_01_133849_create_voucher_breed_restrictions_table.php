<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

// @codingStandardsIgnoreLine
class CreateVoucherBreedRestrictionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('voucher_breed_restrictions', function (Blueprint $table) {
            $table->unsignedInteger('breed_id')->index();
            $table->unsignedInteger('voucher_id')->index();

            $table->foreign('breed_id')->references('id')->on('breeds'); ## FK6
            $table->foreign('voucher_id')->references('id')->on('vouchers'); ## FK15

            $table->unique(['breed_id', 'voucher_id']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('voucher_breed_restrictions');
    }
}
