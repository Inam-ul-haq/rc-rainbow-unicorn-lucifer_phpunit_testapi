<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;

class VoucherUniqueCode extends Model
{
    use LogsActivity;

    protected static $logAttributes = ['*'];
    protected static $logName = 'voucher unique code';
    protected static $logOnlyDirty = true;

    protected $fillable = ['code', 'voucher_id'];

    public function codeUsedOnCoupon()
    {
        return $this->hasOne('App\Coupon', 'vouchers_unique_codes_used_id', 'id'); ## FK13
    }

    public function voucher()
    {
        return $this->belongsTo('App\Voucher'); ## FK12
    }
}
