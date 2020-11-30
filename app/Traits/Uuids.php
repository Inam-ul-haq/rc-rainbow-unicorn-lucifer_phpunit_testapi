<?php

namespace App\Traits;

use Ramsey\Uuid\Uuid;

trait Uuids
{
    protected static function bootUuids()
    {
        static::creating(function ($model) {
            $model->uuid = Uuid::uuid4();
        });
    }

    public function getRouteKeyName()
    {
        return 'uuid';
    }
}
