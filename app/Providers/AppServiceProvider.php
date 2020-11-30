<?php

namespace App\Providers;

use Illuminate\Support\Facades\Schema;
use Spatie\Activitylog\Models\Activity;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        Schema::defaultStringLength(191);  // for those people using MySQL < 5.7.7

        Activity::saving(function (Activity $activity) {
            $activity->ip_address = request()->ip();
        });
    }
}
