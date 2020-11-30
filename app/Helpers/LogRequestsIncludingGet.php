<?php

namespace App\Helpers;

use Illuminate\Http\Request;

class LogRequestsIncludingGet implements \Spatie\HttpLogger\LogProfile
{
    public function shouldLogRequest(Request $request): bool
    {
        return in_array(strtolower($request->method()), ['get', 'post', 'put', 'patch', 'delete']);
    }
}
