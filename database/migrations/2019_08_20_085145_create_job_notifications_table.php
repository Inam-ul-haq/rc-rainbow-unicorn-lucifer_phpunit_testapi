<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

// @codingStandardsIgnoreLine
class CreateJobNotificationsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('job_notifications', function (Blueprint $table) {
            $table->increments('id');
            $table->enum('type', [
                'csv_export',
                'user_data_export',
            ]);
            $table->unsignedInteger('user_id')->nullable();
            $table->string('filename')->nullable();
            $table->string('disk')->nullable();
            $table->string('status')->nullable();
            $table->string('downloadkey', 32)->nullable();
            $table->unsignedTinyInteger('download_count')->default(0);
            $table->unsignedTinyInteger('download_limit')->default(0);
            $table->timestamps();

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
        Schema::dropIfExists('job_notifications');
    }
}
