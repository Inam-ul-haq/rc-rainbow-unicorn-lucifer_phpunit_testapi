<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

// @codingStandardsIgnoreLine
class CreateReferrersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('referrers', function (Blueprint $table) {
            $table->increments('id');
            $table->uuid('uuid')->index();
            $table->string('email', 80)->unique();
            $table->unsignedInteger('name_title_id');
            $table->string('first_name', 100);
            $table->string('last_name', 100);
            $table->integer('referrer_points')->default(0);
            $table->boolean('blacklisted')->default(0);
            $table->datetime('blacklisted_at')->nullable();

            $table->foreign('name_title_id')->references('id')->on('name_titles'); ## FK19

            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('referrers');
    }
}
