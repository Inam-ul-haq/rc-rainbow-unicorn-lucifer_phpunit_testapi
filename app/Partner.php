<?php
namespace App;

use App\User;
use App\Traits\Uuids;
use Illuminate\Http\Request;
use Laravel\Scout\Searchable;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;
use Grimzy\LaravelMysqlSpatial\Eloquent\SpatialTrait;

class Partner extends Model
{
    use Uuids;
    use Searchable;
    use LogsActivity;
    use SpatialTrait;

    protected static $logAttributes = ['*'];
    protected static $logName = 'partner';
    protected static $logOnlyDirty = true;

    protected $fillable = [
        'type',
        'public_name',
        'location_point',
        'contact_name_title_id',
        'contact_first_name',
        'contact_last_name',
        'contact_telephone',
        'contact_email',
        'public_street_line1',
        'public_street_line2',
        'public_town',
        'public_county',
        'public_postcode',
        'public_country',
        'public_email',
        'public_vat_number',
        'accepts_vouchers',
        'accepts_loyalty',
        'crm_id',
        'exclude_from_spatial_search',
    ];

    protected $spatialFields = [
        'location_point',
    ];

    public function toSearchableArray()
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'crm_id' => $this->crm_id,
            'public_name' => $this->public_name,
        ];
    }

    public function contactNameTitle()
    {
        return $this->belongsTo('App\NameTitle'); ## FK30
    }

    // (includes managers)
    public function partnerUsers()
    {
        return $this->belongsToMany('App\User', 'partner_user')
                    ->wherePivot('approved', '=', 1);
    }

    public function partnerManagers()
    {
        return $this->partnerUsers()->wherePivot('manager', '=', 1);
    }

    public function pendingPartnerUsers()
    {
        return $this->belongsToMany('App\User', 'partner_user')
                    ->wherePivot('approved', '=', 0);
    }

    public function groups()
    {
        return $this->belongsToMany('App\PartnerGroup', 'partner_group_members'); ## FK31, FK32
    }

    public function salesReps()
    {
        return $this->belongsToMany('App\User', 'partner_sales_reps');
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
