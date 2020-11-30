<?php

namespace App\Traits;

use Auth;
use App\User;

trait CreatedByUpdatedBy
{
    protected static function bootCreatedByUpdatedBy()
    {
        static::creating(function ($model) {
            // When seeding, $user isn't defined, so we fallback to User::first() in that case.
            $user = Auth::user();
            $model->created_by = $user !== null ? $user->id : User::first()->id;
            $model->updated_by = $user !== null ? $user->id : User::first()->id;
        });

        static::updating(function ($model) {
            $user = Auth::user();
            $model->updated_by = $user !== null ? $user->id : User::first()->id;
        });
    }
}
