<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;

class Breed extends Model
{
    use LogsActivity;

    protected static $logAttributes = ['*'];
    protected static $logName = 'breed';
    protected static $logOnlyDirty = true;

    public function pets()
    {
        return $this->hasMany('App\Pet'); ## FK2
    }

    public function species()
    {
        return $this->belongsTo('App\Species'); ## FK3
    }

    public function voucherRestrictions()
    {
        return $this->belongsTo('App\Voucher', 'voucher_breed_restrictions'); ## FK6, FK15
    }
}
