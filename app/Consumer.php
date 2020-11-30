<?php

namespace App;

use App\Voucher;

use App\Traits\Uuids;
use App\SystemVariable;
use Laravel\Scout\Searchable;
use Illuminate\Support\Carbon;
use Tymon\JWTAuth\Contracts\JWTSubject;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;
use Spatie\Activitylog\Traits\LogsActivity;
use App\Http\Resources\GDPRConsumerResource;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illumintate\Auth\Passwords\CanResetPassword;
use Spatie\PersonalDataExport\ExportsPersonalData;
use Spatie\PersonalDataExport\PersonalDataSelection;
use Illuminate\Foundation\Auth\User as Authenticatable;
use App\Notifications\MailResetConsumerPasswordNotification;

class Consumer extends Authenticatable implements JWTSubject, ExportsPersonalData
{
    use Uuids;
    use Searchable;
    use Notifiable;
    use LogsActivity;
    use SoftDeletes;

    protected static $logAttributes = ['*'];
    protected static $logAttributesToIgnore = ['password'];
    protected static $logName = 'consumer';
    protected static $logOnlyDirty = true;

    protected $fillable = [
        'name_title_id',
        'first_name',
        'last_name',
        'address_line_1',
        'address_line_2',
        'town',
        'county',
        'postcode',
        'country',
        'email',
        'telephone',
        'source',
    ];

    public $asYouType = true;
    public $allowedApiRelations= [ // A list of relationships which can be loaded when requesting the model via the API
        'nameTitle',
        'pets',
        'tags',
        'redeemedCoupons',
        'restrictedCoupons',
    ];

    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims()
    {
        return [];
    }

    public function toSearchableArray()
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'email' => $this->email,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'postcode' => $this->postcode,
        ];
    }

    public function setPasswordAttribute($password)
    {
        if (!empty($password)) {
            $this->attributes['password'] = bcrypt($password);
        }
    }

    public function selectPersonalData(PersonalDataSelection $personalDataSelection): void
    {
        $personalDataSelection
        ->add('user.json', new GDPRConsumerResource($this));
    }

    public function personalDataExportName(): string
    {
    }

    public function nameTitle()
    {
        return $this->belongsTo(NameTitle::class); ## FK9
    }

    public function pets()
    {
        return $this->hasMany(Pet::class); ## FK1
    }

    public function tags()
    {
        return $this->belongsToMany('App\Tag', 'consumer_tag')->withTimestamps(); ## FK51, FK52
    }

    public function consumerEvents()
    {
        return $this->hasMany('App\ConsumerEvent');
    }

    public function redeemedCoupons()
    {
        return $this->hasMany('App\Coupon', 'redeemed_by_consumer_id'); ## FK 8
    }

    public function restrictedCoupons()
    {
        return $this->hasMany('App\Coupon', 'restrict_consumer_id'); ## FK 7
    }

    public function referrerPoints()
    {
        return $this->hasMany('App\ReferrerPoints');
    }

    public function sendPasswordResetNotification($token)
    {
        $reset_url = request('reset_url');
        $this->notify(new MailResetConsumerPasswordNotification([
            'token' => $token,
            'reset_url' => $reset_url,
            'email' => $this->email,
        ]));
    }

    public function validVouchers()
    {
        $vouchers =
            Voucher
            ::whereNull('deleted_at')
            ->get();

        $valid_vouchers = [];
        $invalid_vouchers = [];

        foreach ($vouchers as $voucher) {
            if ($voucher->validForIssue($this)) {
                $valid_vouchers[] = $voucher;
            } else {
                $invalid_vouchers[] = [
                    'uuid' => $voucher->uuid,
                    'name' => $voucher->public_name,
                    'reason' => $voucher->getLastMessage(),
                ];
            }
        }

        return [
            'vouchers_available' => $valid_vouchers,
            'invalid_vouchers' => $invalid_vouchers,
        ];
    }

    public function canLogin()
    {
        $runlevel = SystemVariable::where('variable_name', 'open_mode')->first()->variable_value;

        switch ($runlevel) {
            case 2:
                return true;

            default:
                return false;
        }
    }
}
