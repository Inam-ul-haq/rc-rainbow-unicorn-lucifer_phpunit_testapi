<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

// @codingStandardsIgnoreLine
class CreatePartnerSalesRepsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('partner_sales_reps', function (Blueprint $table) {
            $table->unsignedInteger('partner_id'); ## FK53
            $table->unsignedInteger('user_id'); ## FK54

            $table->foreign('partner_id')->references('id')->on('partners');
            $table->foreign('user_id')->references('id')->on('users');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('partner_sales_reps');
    }
}
