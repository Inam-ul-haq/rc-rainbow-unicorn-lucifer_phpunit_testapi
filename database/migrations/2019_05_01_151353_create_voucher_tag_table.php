<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

// @codingStandardsIgnoreLine
class CreateVoucherTagTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('voucher_tag', function (Blueprint $table) {
            $table->unsignedInteger('voucher_id');
            $table->unsignedInteger('tag_id');
            $table->enum('when_to_subscribe', [ 'signup', 'redemption']);

            $table->foreign('voucher_id')->references('id')->on('vouchers');
            $table->foreign('tag_id')->references('id')->on('tags');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('voucher_tag');
    }
}
