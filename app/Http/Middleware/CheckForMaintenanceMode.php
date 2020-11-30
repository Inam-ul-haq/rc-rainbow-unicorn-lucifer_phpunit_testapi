<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Auth;
use Illuminate\Foundation\Http\Exceptions\MaintenanceModeException;
use Illuminate\Foundation\Http\Middleware\CheckForMaintenanceMode as Middleware;

class CheckForMaintenanceMode extends Middleware
{
    /**
     * The URIs that should be reachable while maintenance mode is enabled.
     *
     * @var array
     */
    protected $except = [
        'api/v0/system/status',
        'api/v0/user/login',
        'api/v0/user/refresh_token',
        'api/v0/user/logout',
    ];

    public function handle($request, Closure $next)
    {
        if ($this->inExceptArray($request)) {
            return $next($request);
        }

        $runlevel = \App\SystemVariable::where('variable_name', 'open_mode')->first()->variable_value;
        if ($runlevel == 2) {
            return $next($request);
        }

        if (Auth::check() and
            $user = Auth::user() and
            $user->canLogin()) {
            return $next($request);
        }

        throw new MaintenanceModeException(null, null, 'Service Unavailable');
    }
}
