<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

// @codingStandardsIgnoreLine
class CreatePartnerGroupMembersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('partner_group_members', function (Blueprint $table) {
            $table->unsignedInteger('partner_id')->index();
            $table->unsignedInteger('partner_group_id')->index();

            $table->foreign('partner_id')->references('id')->on('partners'); ## FK31
            $table->foreign('partner_group_id')->references('id')->on('partner_groups'); ## FK32
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('partner_group_members');
    }
}
