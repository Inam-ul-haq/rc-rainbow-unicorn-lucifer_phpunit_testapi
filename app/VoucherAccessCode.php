<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;

class VoucherAccessCode extends Model
{
    use LogsActivity;

    protected static $logAttributes = ['*'];
    protected static $logName = 'voucher access code';
    protected static $logOnlyDirty = true;

    public function voucher()
    {
        return $this->belongsTo('App\Voucher'); ## FK11
    }

    public function coupons()
    {
        return $this->belongsTo('App\Coupon', 'id', 'access_code_id'); ## FK10
    }
}
