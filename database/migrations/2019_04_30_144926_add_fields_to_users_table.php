<?php

use App\User;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

// @codingStandardsIgnoreLine
class AddFieldsToUsersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('password_change_needed')->default(0);
            $table->boolean('blocked')->default(0);
            $table->datetime('blocked_at')->nullable();
            $table->unsignedInteger('name_title_id')->nullable(); ## FK56

            $table->foreign('name_title_id')->references('id')->on('name_titles');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('password_change_needed');
            $table->dropColumn('blocked');
            $table->dropColumn('blocked_at');
            $table->dropForeign(['name_title_id']);
            $table->dropColumn('name_title_id');
        });
    }
}
