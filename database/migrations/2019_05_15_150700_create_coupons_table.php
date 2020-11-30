<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

// @codingStandardsIgnoreLine
class CreateCouponsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('coupons', function (Blueprint $table) {
            $table->increments('id');
            $table->uuid('uuid')->index();
            $table->datetime('issued_at');
            $table->unsignedInteger('access_code_id')->nullable(); ## FK10
            $table->unsignedInteger('restrict_consumer_id')->nullable(); ## FK7
            $table->unsignedInteger('restrict_partner_id')->nullable(); ## FK27
            $table->unsignedInteger('referrer_id')->nullable(); ## FK25
            $table->unsignedInteger('voucher_id')->nullable(); ## FK23
            $table->string('barcode', 30)->nullable();
            $table->datetime('valid_from');
            $table->datetime('valid_to');
            $table->integer('maximum_uses')->nullable();
            $table->string('shared_code', 20)->nullable();
            $table->datetime('redeemed_datetime')->nullable();
            $table->unsignedInteger('redemption_partner_id')->nullable(); ## FK26
            $table->unsignedInteger('redeemed_by_consumer_id')->nullable(); ## FK8
            $table->integer('redemption_method')->nullable();
            $table->datetime('cancelled_at')->nullable();
            $table->unsignedInteger('vouchers_unique_codes_used_id')->nullable(); ## FK13
            $table->unsignedInteger('reissued_as_coupon_id')->nullable(); ## FK51
            $table->timestamps();

            $table->foreign('access_code_id')->references('id')->on('voucher_access_codes');
            $table->foreign('restrict_consumer_id')->references('id')->on('consumers');
            $table->foreign('restrict_partner_id')->references('id')->on('partners');
            $table->foreign('referrer_id')->references('id')->on('referrers');
            $table->foreign('voucher_id')->references('id')->on('vouchers');
            $table->foreign('redemption_partner_id')->references('id')->on('partners');
            $table->foreign('redeemed_by_consumer_id')->references('id')->on('consumers');
            $table->foreign('vouchers_unique_codes_used_id')->references('id')->on('voucher_unique_codes');
            $table->foreign('reissued_as_coupon_id')->references('id')->on('coupons');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('coupons');
    }
}
