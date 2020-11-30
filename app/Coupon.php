<?php

namespace App;

use App\Traits\Uuids;
use Illuminate\Support\Carbon;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;

class Coupon extends Model
{
    use Uuids;
    use LogsActivity;

    protected static $logAttributes = ['*'];
    protected static $logName = 'coupon';
    protected static $logOnlyDirty = true;

    public $last_error = '';

    protected $fillable = [
        'issued_at',
        'access_code_id',
        'restrict_consumer_id',
        'restrict_partner_id',
        'referrer_id',
        'voucher_id',
        'barcode',
        'valid_from',
        'valid_to',
        'maximum_uses',
        'shared_code',
        'redeemed_datetime',
        'redemption_partner_id',
        'redeemed_by_consumer_id',
        'redemption_method',
        'cancelled_at',
        'vouchers_unique_codes_used_id',
        'reissued_as_coupon_id',
        'status',
        'pet_id',
    ];

    protected $appends = ['reissued_datetime'];

    public function accessCode()
    {
        return $this->hasOne('App\VoucherAccessCode', 'access_code_id'); ## FK10
    }

    public function restrictConsumer()
    {
        return $this->belongsTo('App\Consumer', 'restrict_consumer_id'); ## FK7
    }

    public function restrictPartner()
    {
        return $this->belongsTo('App\Partner', 'restrict_partner_id'); ## FK27
    }

    public function referrer()
    {
        return $this->belongsTo('App\Referrer'); ## FK25
    }

    public function referrerPoints()
    {
        return $this->hasOne('App\ReferrerPoints');
    }

    public function voucher()
    {
        return $this->belongsTo('App\Voucher'); ## FK23
    }

    public function redemptionPartner()
    {
        return $this->belongsTo('App\Partner', 'redemption_partner_id'); ## FK26
    }

    public function redemptionConsumer()
    {
        return $this->belongsTo('App\Consumer', 'redeemed_by_consumer_id'); ## FK8
    }

    public function uniqueVoucherCodeUsed()
    {
        return $this->belongsTo('App\VoucherUniqueCode', 'vouchers_unique_codes_used_id'); ## FK13
    }

    public function reissuedAsCoupon()
    {
        return $this->hasOne('App\Coupon', 'id', 'reissued_as_coupon_id'); ## FK51
    }

    public function originalCoupon()
    {
        return $this->belongsTo('App\Coupon', 'id', 'reissued_as_coupon_id');
    }

    public function getReissuedDatetimeAttribute()
    {
        return isset($this->reissuedAsCoupon) ? $this->reissuedAsCoupon->created_at:null;
    }

    public function isValid()
    {
        if ($this->reissued_as_coupon_id) {
            $reissued = Coupon::where('id', $this->reissued_as_coupon_id)->first();
            $this->last_error = __('Coupon.reissued_as', ['uuid' => $reissued->uuid]);
            return false;
        }

        if ($this->cancelled) {
            $this->last_error = __('Coupon.cancelled');
            return false;
        }

        $date = new Carbon();
        if ($this->valid_from > $date) {
            $this->last_error = __('Coupon.not_valid_yet');
            return false;
        }

        if ($this->valid_to < $date) {
            $this->last_error = __('Coupon.expired');
            return false;
        }

        if ($this->redeemed_datetime) {
            $this->last_error = __('Coupon.already_redeemed');
            return false;
        }

        return true;
    }
}
