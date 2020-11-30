<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

// @codingStandardsIgnoreLine
class CreatePetsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('pets', function (Blueprint $table) {
            $table->increments('id');
            $table->uuid('uuid')->index();
            $table->unsignedInteger('consumer_id')->index();
            $table->string('pet_name', 200);
            $table->date('pet_dob');
            $table->unsignedInteger('breed_id')->index();
            $table->enum('pet_gender', [ 'male', 'female' ]);
            $table->boolean('neutered')->nullable();

            $table->foreign('consumer_id')->references('id')->on('consumers'); ## FK1
            $table->foreign('breed_id')->references('id')->on('breeds');  ## FK2

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
        Schema::dropIfExists('pets');
    }
}
