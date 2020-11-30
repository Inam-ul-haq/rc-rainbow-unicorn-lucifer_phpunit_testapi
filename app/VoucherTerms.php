<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;

class VoucherTerms extends Model
{
    use LogsActivity;
    use \Bkwld\Cloner\Cloneable;

    protected static $logAttributes = ['*'];
    protected static $logName = 'voucher terms';
    protected static $logOnlyDirty = true;

    protected $table = 'voucher_terms';

    protected $fillable = [
        'voucher_id',
        'voucher_terms',
        'used_from',
        'used_to',
    ];

    public function voucher()
    {
        return $this->belongsTo('Voucher'); ## FK22
    }

    public function onCloning($src, $child = null)
    {
        $this->used_from = now();
        $this->used_until = null;
    }
}
