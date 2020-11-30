<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;

class PetWeights extends Model
{
    protected $table = 'pet_weights';

    protected static $logAttributes = ['*'];
    protected static $logName = 'pet weights';
    protected static $logOnlyDirty = true;

    public function pet()
    {
        return $this->belongsTo('App\Pet');
    }
}
