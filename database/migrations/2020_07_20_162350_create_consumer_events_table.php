<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// @codingStandardsIgnoreLine
class CreateConsumerEventsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('consumer_events', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('consumer_id')->index();
            $table->string('event', 100)->index();
            $table->string('event_source', 100)->index();
            $table->json('properties')->nullable();
            $table->boolean('imported_from_clixray')->default(0);
            $table->timestamp('created_at', 0)->nullable();

            $table->foreign('consumer_id')->references('id')->on('consumers');

            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('consumer_events');
    }
}
