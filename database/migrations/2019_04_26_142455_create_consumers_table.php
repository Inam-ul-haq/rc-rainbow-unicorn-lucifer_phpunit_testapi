<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

// @codingStandardsIgnoreLine
class CreateConsumersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('consumers', function (Blueprint $table) {
            $table->increments('id');
            $table->uuid('uuid')->index();
            $table->unsignedInteger('name_title_id')->nullable();
            $table->string('first_name', 50)->index()->nullable();
            $table->string('last_name', 50)->index()->nullable();
            $table->integer('crm_id')->index()->nullable();
            $table->datetime('last_update_from_crm')->nullable();
            $table->datetime('optin_email_sent_date')->nullable();
            $table->datetime('optin_email_reminder_send_date')->nullable();
            $table->datetime('email_optin_date')->nullable();
            $table->string('address_line_1', 100)->nullable();
            $table->string('town', 100)->nullable();
            $table->string('county', 100)->nullable();
            $table->string('country', 100)->nullable();
            $table->string('postcode', 20)->nullable();
            $table->string('email', 80)->index();
            $table->string('telephone', 40)->index()->nullable();
            $table->string('password', 255)->nullable();
            $table->datetime('gdpr_optin_email_date')->nullable();
            $table->datetime('gdpr_optin_phone_date')->nullable();
            $table->boolean('blacklisted')->default(0);
            $table->datetime('blacklisted_at')->nullable();
            $table->boolean('active')->default(1);
            $table->datetime('deactivated_at')->nullable();
            $table->string('deleted')->default(0);
            $table->unique(['email','deleted']);
            $table->boolean('password_change_needed')->default(0);
            $table->string('source')->index();

            $table->foreign('name_title_id')->references('id')->on('name_titles');  ## FK9

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
        Schema::dropIfExists('consumers');
    }
}
