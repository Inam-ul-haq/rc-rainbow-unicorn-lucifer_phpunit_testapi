<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

// @codingStandardsIgnoreLine
class CreateVouchersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('vouchers', function (Blueprint $table) {
            $table->increments('id');
            $table->uuid('uuid')->index();
            $table->string('url', 50)->unique()->index();
            $table->string('name', 100);
            $table->boolean('published')->default(0);
            $table->integer('value_gbp');
            $table->integer('value_eur');
            $table->date('subscribe_from_date');
            $table->date('subscribe_to_date')->nullable();
            $table->date('redeem_from_date')->nullable();
            $table->date('redeem_to_date')->nullable();
            $table->integer('redemption_period_count')->nullable();
            $table->enum('redemption_period', ['days', 'months', 'years'])->default('days')->nullable();
            $table->string('public_name', 100);
            $table->text('page_copy')->nullable();
            $table->string('page_copy_image')->nullable();
            $table->string('valassis_barcode')->nullable();
            $table->string('valassis_pin')->nullable();
            $table->boolean('unique_code_required')->default(0)->nullable();
            $table->string('unique_code_prefix', 10)->nullable();
            $table->string('unique_codes_url')->nullable();
            $table->integer('retrieve_unique_codes_every_count')->nullable();
            $table->enum('retrieve_unique_codes_every_type', ['hours', 'days', 'months', 'years'])
                ->default('hours')
                ->nullable();
            $table->time('retrieve_unique_codes_every_day_at_time')->nullable();
            $table->datetime('unique_codes_last_retrieve_date')->nullable();
            $table->integer('referrer_points_at_create')->default(0)->nullable();
            $table->integer('referrer_points_at_redeem')->default(0)->nullable();
            $table->integer('limit_per_account')->default(0)->nullable();
            $table->smallInteger('limit_per_account_per_date_period')->default(0)->nullable();
            $table->boolean('limit_pet_required')->default(0)->nullable();
            $table->integer('limit_per_pet')->default(0)->nullable();
            $table->unsignedInteger('limit_species_id')->nullable();
            $table->boolean('send_by_email')->default(0)->nullable();
            $table->string('email_subject_line')->nullable();
            $table->text('email_copy')->nullable();
            $table->string('email_copy_image')->nullable();
            $table->unsignedInteger('created_by');
            $table->unsignedInteger('updated_by');
            $table->boolean('instant_redemption')->default(0);
            $table->boolean('limit_to_instant_redemption_partner')->default(0);
            $table->string('unique_code_label')->nullable();
            $table->string('unique_code_placeholder')->nullable();
            $table->foreign('limit_species_id')->references('id')->on('species');
            $table->foreign('created_by')->references('id')->on('users');
            $table->foreign('updated_by')->references('id')->on('users');
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
        Schema::dropIfExists('vouchers');
    }
}
