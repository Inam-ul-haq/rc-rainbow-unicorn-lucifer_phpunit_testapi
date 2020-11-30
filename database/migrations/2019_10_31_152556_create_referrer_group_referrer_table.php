<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

// @codingStandardsIgnoreLine
class CreateReferrerGroupReferrerTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('referrer_group_referrer', function (Blueprint $table) {
            $table->unsignedBigInteger('referrer_group_id')->index();
            $table->unsignedInteger('referrer_id')->index();

            $table->foreign('referrer_group_id')->references('id')->on('referrer_groups');
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
        Schema::dropIfExists('referrer_group_referrer');
    }
}
