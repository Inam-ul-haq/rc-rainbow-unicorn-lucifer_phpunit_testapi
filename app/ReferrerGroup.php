<?php

namespace App;

use App\Group;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;

class ReferrerGroup extends Group
{
    protected static $logName = 'referrer group';

    public function referrers()
    {
        return $this->belongsToMany('App\Referrer', 'referrer_group_referrer');
    }

    public function voucherRestrictions()
    {
        return $this->belongsToMany('App\Voucher', 'referrer_group_voucher_restriction');
    }
}
