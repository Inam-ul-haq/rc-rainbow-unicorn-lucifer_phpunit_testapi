<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;

class Product extends Model
{
    protected static $logAttributes = ['*'];
    protected static $logName = 'product';
    protected static $logOnlyDirty = true;

    public function voucherRestrictions()
    {
        return $this->belongsToMany('App\Voucher', 'voucher_product_restrictions'); ## FK20, FK21
    }
}
