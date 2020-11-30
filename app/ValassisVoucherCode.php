<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;

class ValassisVoucherCode extends Model
{
    use LogsActivity;

    public $timestamps = false;

    protected static $logAttributes = ['*'];
    protected static $logName = 'valassis voucher code';
    protected static $logOnlyDirty = true;


    public function voucher()
    {
        return $this->belongsTo('App\Voucher');
    }
}
