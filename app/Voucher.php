<?php

namespace App;

use DB;
use Auth;
use App\Traits\Uuids;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use App\Traits\CreatedByUpdatedBy;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;
use Grimzy\LaravelMysqlSpatial\Types\Point;

class Voucher extends Model
{
    use Uuids;
    use LogsActivity;
    use CreatedByUpdatedBy;
    use \Bkwld\Cloner\Cloneable;

    private $lastMessage;

    protected static $logAttributes = ['*'];
    protected static $logName = 'voucher';
    protected static $logOnlyDirty = true;
    protected $cloneable_relations = [
        'productRestrictions',
        'breedRestrictions',
        'speciesRestrictions',
        'partnerGroupRestrictions',
        'referrerGroupRestrictions',
        'referrerRestrictions',
        'apiAccountRestrictions',
        'tags',
    ];

    protected $appends = [
        'page_copy_image_url',
    ];

    protected $fillable = [
        'url',
        'name',
        'page_copy',
        'value_gbp',
        'value_eur',
        'published',
        'email_copy',
        'public_name',
        'valassis_pin',
        'limit_per_pet',
        'send_by_email',
        'redeem_to_date',
        'page_copy_image',
        'limit_species_id',
        'redeem_from_date',
        'unique_codes_url',
        'valassis_barcode',
        'limit_per_account',
        'redemption_period',
        'subscribe_to_date',
        'email_subject_line',
        'limit_pet_required',
        'unique_code_prefix',
        'instant_redemption',
        'subscribe_from_date',
        'unique_code_required',
        'redemption_period_count',
        'referrer_points_at_create',
        'referrer_points_at_redeem',
        'retrieve_unique_codes_every_type',
        'limit_per_account_per_date_period',
        'retrieve_unique_codes_every_count',
        'limit_to_instant_redemption_partner',
        'retrieve_unique_codes_every_day_at_time',
    ];

    protected $spatialFields = [
        'location_point',
    ];

    public function terms()
    {
        return $this->hasMany('App\VoucherTerms'); ## FK22
    }

    public function currentTerms()
    {
        return $this->hasOne('App\VoucherTerms')->whereNull('used_until');
    }

    public function productRestrictions()
    {
        return $this->belongsToMany('App\Product', 'voucher_product_restrictions'); ## FK20, FK21
    }

    public function valassisVoucherCodes()
    {
        return $this->hasMany('App\ValassisVoucherCode'); ## FK14
    }

    public function breedRestrictions()
    {
        return $this->belongsToMany('App\Breed', 'voucher_breed_restrictions'); ## FK6, FK15
    }

    public function speciesRestrictions()
    {
        return $this->belongsToMany('App\Species', 'voucher_species_restrictions'); ## FK4, FK16
    }

    public function partnerGroupRestrictions()
    {
        return $this->belongsToMany('App\PartnerGroup', 'voucher_partner_group_restrictions'); ## FK24, FK33
    }

    public function partnerRestrictionsThroughGroups()
    {
        return $this->hasManyThrough('App\PartnerGroup', 'App\Partner');
    }

    public function referrerGroupRestrictions()
    {
        return $this->belongsToMany('App\ReferrerGroup', 'referrer_group_voucher_restriction');
    }

    public function referrerRestrictions()
    {
        return $this->belongsToMany('App\Referrer', 'voucher_referrer_restrictions');
    }

    public function apiAccountRestrictions()
    {
        return $this->belongsToMany('App\User', 'voucher_api_account_restrictions'); ## FK17, FK18
    }

    public function tags()
    {
        return $this->belongsToMany('App\Tag', 'voucher_tag')
                ->withPivot('when_to_subscribe'); ## FK6, FK7
    }

    public function accessCodes()
    {
        return $this->hasMany('App\VoucherAccessCode'); ## FK11
    }

    public function coupons()
    {
        return $this->hasMany('App\Coupon'); ## FK23
    }

    public function referrerPoints()
    {
        return $this->hasManyThrough('App\ReferrerPoints', 'App\Coupon');
    }

    public function onCloning($src, $child = null)
    {
        $valid_url_found = 0;
        for ($i=1; $i<100; $i++) {  // If we can't find a unique URL after 100 tries, something's probably wrong!
            if (DB::table('vouchers')->where('url', '=', "copy_{$i}_of_{$src->url}")->count() == 0) {
                $this->url = "copy_{$i}_of_{$src->url}";
                $valid_url_found++;
                break;
            }
        }

        if ($valid_url_found == 0) {
            throw new \Exception('Unable to generate unique URL');
        }

        $this->name = "(Copy of) {$src->name}";
        $this->published = 0;
    }

    public function createdBy()
    {
        return $this->belongsTo('App\User', 'created_by');
    }

    public function updatedBy()
    {
        return $this->belongsTo('App\User', 'updated_by');
    }

    public function validPartners($search_term = '')
    {
        return $this->partnerGroupRestrictions->count() ?
        DB::table('vouchers')
        ->distinct()
        ->join(
            'voucher_partner_group_restrictions',
            'vouchers.id',
            '=',
            'voucher_partner_group_restrictions.voucher_id'
        )
        ->join(
            'partner_group_members',
            'voucher_partner_group_restrictions.partner_group_id',
            '=',
            'partner_group_members.partner_group_id'
        )
        ->join(
            'partners',
            'partner_group_members.partner_id',
            '=',
            'partners.id'
        )
        ->select(
            [
                'partners.public_name',
                'partners.uuid',
                'partners.public_town',
                'partners.crm_id',
            ]
        )
        ->where('vouchers.id', '=', $this->id)
        ->where('partners.accepts_vouchers', '=', 1)
        ->whereNull('partners.deleted_at')
        ->when(
            $search_term,
            function ($q, $search_term) {
                return $q->where(function ($query) use ($search_term) {
                    $query->where('partners.public_name', 'like', "%{$search_term}%")
                          ->orWhere('partners.crm_id', 'like', "%{$search_term}%")
                          ->orWhere('partners.uuid', 'like', $search_term);
                });
            }
        )
        ->orderBy('public_name', 'asc')
        ->orderBy('public_town', 'asc')
        ->get()

        :

        DB::table('partners')
            ->select(
                [
                    'partners.public_name',
                    'partners.uuid',
                    'partners.public_town',
                    'partners.crm_id',
                ]
            )
            ->where('partners.accepts_vouchers', '=', 1)
            ->whereNull('partners.deleted_at')
            ->when(
                $search_term,
                function ($q, $search_term) {
                    return $q->where(function ($query) use ($search_term) {
                        $query->where('partners.public_name', 'like', "%{$search_term}%")
                        ->orWhere('partners.crm_id', 'like', "%{$search_term}%")
                        ->orWhere('partners.uuid', 'like', $search_term);
                    });
                }
            )
            ->orderBy('public_name', 'asc')
            ->orderBy('public_town', 'asc')
            ->get();
    }

    public function validPartnersByDistance(float $lat, float $long, int $per_page, $search_term = '')
    {
        return $this->partnerGroupRestrictions->count() ?
        DB::table('vouchers')
        ->distinct()
        ->join(
            'voucher_partner_group_restrictions',
            'vouchers.id',
            '=',
            'voucher_partner_group_restrictions.voucher_id'
        )
        ->join(
            'partner_group_members',
            'voucher_partner_group_restrictions.partner_group_id',
            '=',
            'partner_group_members.partner_group_id'
        )
        ->join(
            'partners',
            'partner_group_members.partner_id',
            '=',
            'partners.id'
        )
        ->select(
            [
                'partners.public_name',
                'partners.uuid',
                'partners.crm_id',
                'partners.type',
                'partners.accepts_loyalty',
                'partners.public_street_line1',
                'partners.public_street_line2',
                'partners.public_street_line3',
                'partners.public_town',
                'partners.public_county',
                'partners.public_postcode',
                'partners.latitude',
                'partners.longitude',
            ]
        )
        ->selectRaw(
            "ROUND(
                ST_Distance_Sphere(
                    partners.location_point,
                    ST_GeomFromText('POINT($lat $long)', 4326)
                ),
                0
            ) AS distance"
        )
        ->where('vouchers.id', '=', $this->id)
        ->where('partners.accepts_vouchers', '=', 1)
        ->whereNull('partners.deleted_at')
        ->when(
            $search_term,
            function ($q, $search_term) {
                return $q->where(function ($query) use ($search_term) {
                    $query->where('partners.public_name', 'like', "%{$search_term}%")
                          ->orWhere('partners.crm_id', 'like', "%{$search_term}%")
                          ->orWhere('partners.uuid', 'like', $search_term);
                });
            }
        )
        ->orderBy('distance')
        ->paginate(10)

        :

        DB::table('partners')
            ->select(
                [
                    'partners.public_name',
                    'partners.uuid',
                    'partners.crm_id',
                    'partners.type',
                    'partners.accepts_loyalty',
                    'partners.public_street_line1',
                    'partners.public_street_line2',
                    'partners.public_street_line3',
                    'partners.public_town',
                    'partners.public_county',
                    'partners.public_postcode',
                    'partners.latitude',
                    'partners.longitude',
                ]
            )
            ->selectRaw(
                "ROUND(
                    ST_Distance_Sphere(
                        partners.location_point,
                        ST_GeomFromText('POINT($lat $long)', 4326)
                    ),
                    0
                ) AS distance"
            )
            ->where('partners.accepts_vouchers', '=', 1)
            ->whereNull('partners.deleted_at')
            ->when(
                $search_term,
                function ($q, $search_term) {
                    return $q->where(function ($query) use ($search_term) {
                        $query->where('partners.public_name', 'like', "%{$search_term}%")
                        ->orWhere('partners.crm_id', 'like', "%{$search_term}%")
                        ->orWhere('partners.uuid', 'like', $search_term);
                    });
                }
            )
            ->orderBy('distance')
            ->paginate(10);
    }

    public function validReferrers($search_term = '')
    {
        $referrer_group_query =
            DB::table('vouchers')
                ->distinct()
                ->join(
                    'referrer_group_voucher_restriction',
                    'vouchers.id',
                    '=',
                    'referrer_group_voucher_restriction.voucher_id'
                )
                ->join(
                    'referrer_group_referrer',
                    'referrer_group_voucher_restriction.referrer_group_id',
                    '=',
                    'referrer_group_referrer.referrer_group_id'
                )
                 ->join(
                     'referrers',
                     'referrer_group_referrer.referrer_id',
                     '=',
                     'referrers.id'
                 )
            ->select(['referrers.first_name', 'referrers.last_name', 'referrers.uuid'])
            ->where('vouchers.id', '=', $this->id)
            ->whereNull('referrers.deleted_at')
            ->when(
                $search_term,
                function ($q, $search_term) {
                    return $q->where(function ($query) use ($search_term) {
                        $query->where('referrers.first_name', 'like', "%{$search_term}%")
                            ->orWhere('referrers.last_name', 'like', "%{$search_term}%")
                            ->orWhere('referrers.email', 'like', "%{$search_term}%")
                            ->orWhere('referrers.uuid', 'like', $uuid);
                    });
                }
            )
             ->orderBy('last_name', 'asc')
             ->orderBy('first_name', 'asc');

        $referrers =
            DB::table('vouchers')
            ->join(
                'voucher_referrer_restrictions',
                'vouchers.id',
                '=',
                'voucher_referrer_restrictions.voucher_id'
            )
            ->join(
                'referrers',
                'voucher_referrer_restrictions.referrer_id',
                '=',
                'referrers.id'
            )
            ->select(['referrers.first_name', 'referrers.last_name', 'referrers.uuid'])
            ->where('vouchers.id', '=', $this->id)
            ->whereNull('referrers.deleted_at')
            ->when(
                $search_term,
                function ($q, $search_term) {
                    return $q->where(function ($query) use ($search_term) {
                        $query->where('referrers.first_name', 'like', "%{$search_term}%")
                            ->orWhere('referrers.last_name', 'like', "%{$search_term}%")
                            ->orWhere('referrers.email', 'like', "%{$search_term}%")
                            ->orWhere('referrers.uuid', 'like', $search_term);
                    });
                }
            )
            ->orderBy('last_name', 'asc')
            ->orderBy('first_name', 'asc')
            ->union($referrer_group_query)
            ->get();

        if ($referrers->count()) {
            return $referrers;
        }

        // No breeder restrictions apply, so return all of them.
        return DB::table('referrers')
            ->select(['referrers.id','referrers.first_name', 'referrers.last_name', 'referrers.uuid'])
            ->whereNull('referrers.deleted_at')
            ->when(
                $search_term,
                function ($q, $search_term) {
                    return $q->where(function ($query) use ($search_term) {
                        $query->where('referrers.first_name', 'like', "%{$search_term}%")
                            ->orWhere('referrers.last_name', 'like', "%{$search_term}%")
                            ->orWhere('referrers.email', 'like', "%{$search_term}%")
                            ->orWhere('referrers.uuid', 'like', $search_term);
                    });
                }
            )
            ->orderBy('last_name', 'asc')
            ->orderBy('first_name', 'asc')
            ->get();
    }

    public function getPageCopyImageUrlAttribute()
    {
        if ($this->page_copy_image) {
            return env('PUBLIC_WEB_ADDRESS') . $this->page_copy_image;
        }
        return null;
    }

    public function validForIssue(Consumer $consumer = null)
    {
        if ($this->published === 0) {
            $this->lastMessage = 'Voucher is not published';
            return false;
        }

        $date = new Carbon();
        if ($date < $this->subscribe_from_date) {
            $this->lastMessage = 'Voucher start date not yet reached';
            return false;
        }

        if ($this->subscribe_to_date and $date > $this->subscribe_to_date) {
            $this->lastMessage = 'Voucher end date passed';
            return false;
        }

        if ($this->limit_pet_required and
            $consumer->pets()->count() === 0) {
            $this->lastMessage = 'Pet is required, but consumer has no pets';
            return false;
        }

        if ($this->limit_per_account !== 0) {
            if ($consumer->restrictedCoupons()
                         ->where('coupons.voucher_id', '=', $this->id)
                         ->when($this->limit_per_account_per_date_period, function ($query) {
                            if ($this->limit_per_account_per_date_period === 0) {
                                $query->where(
                                    'coupons.issued_at',
                                    '<=',
                                    DB::raw('DATE_SUB(NOW(), ' . $this->limit_per_account_per_date_period . ')')
                                );
                            } else {
                                $query->where(
                                    'coupons.issued_at',
                                    '<=',
                                    DB::raw('DATE_SUB(NOW(), ' . $this->limit_per_account_per_date_period . ')')
                                )
                                ->where('coupons.valid_to', '=>', $date);
                            }
                         })
                         ->count()
                     >= $this->limit_per_account) {
                $this->lastMessage = $this->limit_per_account_per_date_period ?
                    "Limit {$this->limit_per_account} each {$this->limit_per_account_per_date_period} months reached" :
                    'Limit per account (lifetime) reached';
                return false;
            }
        }

        if ($this->limit_species_id and
            $consumer
                ->pets()
                ->join('breeds', 'pets.breed_id', '=', 'breeds.id')
                ->join('species', 'breeds.species_id', '=', 'species.id')
                ->where('species_id', '=', $this->limit_species_id)->count() === 0) {
            $this->lastMessage = 'Consumer has no pets of required species id';
            return false;
        }

        return true;
    }

    public function getLastMessage()
    {
        return $this->lastMessage;
    }

    public function uniqueCodes(Request $request = null)
    {
        $codes = VoucherUniqueCode::where('voucher_id', $this->id)
             ->with('CodeUsedOnCoupon');

        if ($request === null) {
            return $codes->get();
        }

        if ($request->input('search')) {
            $codes = $codes->where('code', 'LIKE', '%' . $request->input('search') . '%');
        }

        return $codes->simplePaginate($request->input('per_page', 100));
    }

    public function subscribers(Request $request = null)
    {
        $subscribers = DB::table('coupons')
            ->select(
                'coupons.uuid AS coupon_uuid',
                'consumers.uuid AS consumer_uuid',
                'consumers.first_name AS first_name',
                'consumers.last_name AS last_name',
                'vouchers.public_name AS voucher_name',
                'coupons.status AS status',
                'voucher_unique_codes.code AS unique_code_used',
                'voucher_access_codes.access_code AS access_code_used',
                'coupons.issued_at',
                'coupons.redeemed_datetime AS redeemed_at'
            )
            ->join('consumers', function ($join) {
                $join->on('consumers.id', '=', 'coupons.restrict_consumer_id');
                $join->orOn('consumers.id', '=', 'redeemed_by_consumer_id');
            })
            ->join(
                'voucher_unique_codes',
                'coupons.vouchers_unique_codes_used_id',
                '=',
                'voucher_unique_codes.id',
                'left outer'
            )
            ->join(
                'voucher_access_codes',
                'coupons.access_code_id',
                '=',
                'voucher_access_codes.id',
                'left outer'
            )
            ->join(
                'vouchers',
                'coupons.voucher_id',
                '=',
                'vouchers.id'
            )
            ->where('coupons.voucher_id', '=', $this->id);

        if ($request === null) {
            return $subscribers->get();
        }

        if ($request->input('search')) {
            $subscribers = $subscribers->where('consumers.last_name', 'LIKE', '%' . $request->input('search') . '%')
                                       ->orWhere('consumers.uuid', 'LIKE', $request->input('search'));
        }
        return $subscribers->paginate($request->input('per_page', 100));
    }
}
