<?php

use App\Referrer;
use Ramsey\Uuid\Uuid;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// @codingStandardsIgnoreLine
class AddUuidToJobsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('job_notifications', function (Blueprint $table) {
            $table->uuid('uuid')->default('')->index();
        });

        foreach (App\JobNotification::get() as $job) {
            $job->uuid = UUID::uuid4();
            $job->save();
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('job_notifications', function (Blueprint $table) {
            $table->dropColumn('uuid');
        });
    }
}
