<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

// @codingStandardsIgnoreLine
class CreateVoucherSpeciesRestrictions extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('voucher_species_restrictions', function (Blueprint $table) {
            $table->unsignedInteger('species_id');
            $table->unsignedInteger('voucher_id');

            $table->foreign('species_id')->references('id')->on('species'); ## FK4
            $table->foreign('voucher_id')->references('id')->on('vouchers'); ## FK16

            $table->unique(['species_id', 'voucher_id']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('voucher_species_restrictions');
    }
}
