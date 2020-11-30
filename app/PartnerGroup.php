<?php

namespace App;

use App\Group;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;

class PartnerGroup extends Group
{
    protected static $logName = 'partner group';
    protected $fillable = [
        'group_ref',
        'group_name',
        'managed_remotely',
    ];

    public function partners()
    {
        return $this->belongsToMany('App\Partner', 'partner_group_members'); ## FK31, FK32
    }

    public function voucherRestrictions()
    {
        return $this->belongsToMany('App\Voucher', 'voucher_partner_group_restrictions'); ## FK24, FK33
    }
}
