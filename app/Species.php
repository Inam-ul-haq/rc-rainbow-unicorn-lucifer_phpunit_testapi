<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;

class Species extends Model
{
    use LogsActivity;

    protected static $logAttributes = ['*'];
    protected static $logName = 'species';
    protected static $logOnlyDirty = true;

    public function breeds()
    {
        return $this->hasMany('App\Breed'); ## FK3
    }

    public function voucherRestrictions()
    {
        return $this->belongsTo('App\Voucher', 'voucher_species_restrictions'); ## FK4, FK16
    }
}
