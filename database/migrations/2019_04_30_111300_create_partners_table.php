<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

// @codingStandardsIgnoreLine
class CreatePartnersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('partners', function (Blueprint $table) {
            $table->increments('id');
            $table->uuid('uuid')->index();
            $table->enum('type', ['retailer', 'vet']);
            $table->string('subtype', 100)->nullable();
            $table->string('public_name', 100);
            $table->point('location_point', 4326);
            $table->string('latitude')->virtualAs('ST_X(location_point)');
            $table->string('longitude')->virtualAs('ST_Y(location_point)');
            $table->boolean('exclude_from_spatial_search')->default(1);
            $table->unsignedInteger('contact_name_title_id')->nullable();
            $table->string('contact_first_name', 50)->nullable();
            $table->string('contact_last_name', 50)->nullable();
            $table->string('contact_telephone', 40)->nullable();
            $table->string('contact_email', 80)->index();
            $table->string('public_street_line1', 100);
            $table->string('public_street_line2', 100)->nullable();
            $table->string('public_street_line3', 100)->nullable();
            $table->string('public_town', 100)->nullable();
            $table->string('public_county', 100)->nullable();
            $table->string('public_postcode', 20)->nullable();
            $table->enum('public_country', [
                'Channel Islands',
                'Isle of Man',
                'Republic of Ireland',
                'United Kingdom',
            ]);
            $table->string('public_email', 80)->index();
            $table->string('public_vat_number', 20)->nullable();
            $table->boolean('accepts_vouchers')->default(0);
            $table->boolean('accepts_loyalty')->default(0);
            $table->string('access_question', 255)->nullable();
            $table->string('access_password', 255)->nullable();
            $table->string('crm_id', 40)->unique()->index();

            $table->foreign('contact_name_title_id')->references('id')->on('name_titles');  ## FK30

            $table->spatialIndex('location_point');

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
        if (Schema::hasColumn('partners', 'name_title_id')) {
            Schema::table('partners', function (Blueprint $table) {
                $table->dropForeign('contact_name_title_id');
            });
        }
        Schema::dropIfExists('partners');
    }
}
