<?php

namespace App;

use App\Traits\Uuids;
use Illuminate\Http\Request;
use Laravel\Scout\Searchable;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;
use Illuminate\Database\Eloquent\SoftDeletes;

class Referrer extends Model
{
    use Uuids;
    use Searchable;
    use SoftDeletes;
    use LogsActivity;

    protected static $logAttributes = ['*'];
    protected static $logName = 'referrer';
    protected static $logOnlyDirty = true;

    public function toSearchableArray()
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'email' => $this->email,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
        ];
    }

    public function nameTitle()
    {
        return $this->belongsTo('App\NameTitle'); ## FK19
    }

    // (includes managers)
    public function referrerUsers()
    {
        return $this->belongsToMany('App\User', 'partner_user')
                    ->wherePivot('approved', '=', 1);
    }

    public function referrerGroups()
    {
        return $this->belongsToMany('App\ReferrerGroup', 'referrer_group_referrer');
    }

    public function voucherRestrictions()
    {
        return $this->belongsToMany('App\Voucher', 'voucher_referrer_restrictions');
    }

    public function referrerPoints()
    {
        return $this->hasMany('App\ReferrerPoints');
    }

    public function couponRedemptions(Request $request)
    {
        return Coupon::where('redemption_partner_id', $this->id)
                     ->with('voucher')
                     ->when($request->input('start_date'), function ($query) use ($request) {
                        return $query->where('redeemed_datetime', '>=', $request->input('start_date') . ' 00:00:00');
                     })
                     ->when($request->input('end_date'), function ($query) use ($request) {
                         return $query->where('redeemed_datetime', '<=', $request->input('end_date') . ' 23:59:59');
                     })
                     ->when($request->input('search'), function ($query) use ($request) {
                         return $query->where('barcode', 'LIKE', "%{$search}%");
                     })
                     ->orderBy('redeemed_datetime', 'desc')
                     ->paginate($request->input('per_page', 10));
    }
}
