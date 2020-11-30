<?php

namespace App;

use App\Traits\Uuids;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;

class Pet extends Model
{
    use Uuids;
    use LogsActivity;

    protected static $logAttributes = ['*'];
    protected static $logName = 'pet';
    protected static $logOnlyDirty = true;

    protected $fillable = [
        'consumer_id',
        'pet_name',
        'pet_dob',
        'breed_id',
        'pet_gender',
    ];

    public function consumer()
    {
        return $this->belongsTo('App\Consumer'); ## FK1
    }

    public function breed()
    {
        return $this->belongsTo('App\Breed'); ## FK2
    }

    public function weights()
    {
        return $this->hasMany('App\PetWeights'); ## FK5
    }

    public function coupons()
    {
        return $this->hasMany('App\Coupon');
    }
}
