<?php

namespace App;

use App\Partner;
use App\Traits\Uuids;
use App\SystemVariable;
use Laravel\Scout\Searchable;
use Laravel\Passport\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;
use Tymon\JWTAuth\Contracts\JWTSubject;
use Illuminate\Notifications\Notifiable;
use Spatie\Activitylog\Traits\LogsActivity;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Notifications\MailResetPasswordNotification;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable implements JWTSubject
{
    use HasRoles;
    use Notifiable;
    use SoftDeletes;
    use LogsActivity;
    use Uuids;
    use HasApiTokens;
    /**
     * There seems to be some form of incompatibiltiy between Laravel Scout (or perhaps TNTSearch)
     * and spatie/laravel-permission at the moment. At some point in the future, we can try
     * re-enabling this, and see if someones pushed out a fix in the meantime.
     * use Searchable;
     */

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'email_verified_at',
        'password_change_needed',
        'blocked',
        'name_title_id',
        'partner_id',
        'pending_approval',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password', 'remember_token',
    ];

    protected static $logAttributes = ['*'];
    protected static $logAttributesToIgnore = ['password'];
    protected static $logName = 'user';
    protected static $logOnlyDirty = true;

    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims()
    {
        return [];
    }

    public function setPasswordAttribute($password)
    {
        if (!empty($password)) {
            $this->attributes['password'] = bcrypt($password);
        }
    }

    public function toSearchableArray()
    {
        $this->roles;
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'name' => $this->name,
            'email' => $this->email,
            'roles' => $this->roles->pluck('name'),
        ];
    }

    public function voucherRestrictions()
    {
        return $this->belongsToMany('App\Voucher', 'voucher_api_account_restrictions'); ## FK17, FK18
    }

    public function salesRepPartners()
    {
        return $this->belongsToMany('App\Partner', 'partner_sales_reps');
    }

    public function userPartners()
    {
        return $this->belongsToMany('App\Partner', 'partner_user');
    }

    public function nameTitle()
    {
        return $this->belongsTo('App\NameTitle');
    }

    public function jobNotifications()
    {
        return $this->hasMany('App\JobNotification');
    }

    public function createdVouchers()
    {
        return $this->hasMany('App\Voucher', 'created_by');
    }

    public function updatedVouchers()
    {
        return $this->hasMany('App\Voucher', 'updated_by');
    }

    public function sendPasswordResetNotification($token)
    {
        $reset_url = request('reset_url'); // This should be the full URL, including query
                                           // string variable name (and equals sign).
                                           // $token is directly appended to this.
        $this->notify(new MailResetPasswordNotification([
            'token' => $token,
            'reset_url' => $reset_url,
            'email' => $this->email,
        ]));
    }

    /**
     * Is this user a manager of $partner, and is $user a member of that partner
     */
    public function isAPartnerManagerOfUserForPartner(Partner $partner, User $user)
    {
        return $this->userPartners()
                    ->wherePivot('partner_id', '=', $partner->id)
                    ->wherePivot('manager', '=', 1)
                    ->wherePivot('approved', '=', 1)
                    ->get()
                    ->intersect($user->userPartners()
                                     ->wherePivot('approved', '=', 1)
                                     ->get())
                    ->count()
                    ? true
                    : false;
    }

    /**
     * Is this user a manager of any partners of which $user is a member?
     */
    public function isAPartnerManagerOfUser(User $user)
    {
        return $this->userPartners()
                    ->wherePivot('manager', '=', 1)
                    ->wherePivot('approved', '=', 1)
                    ->get()
                    ->intersect($user->userPartners()
                                     ->wherePivot('approved', '=', 1)
                                     ->get())
                    ->count()
                    ? true
                    : false;
    }

    /**
     * Is this user a manager of $partner?
     */
    public function isAPartnerManagerOfPartner(Partner $partner)
    {
        return $this->userPartners()
                    ->where('partner_id', '=', $partner->id)
                    ->wherePivot('manager', '=', 1)
                    ->wherePivot('approved', '=', 1)
                    ->get()
                    ->count()
                    ? true
                    : false;
    }

    /**
     * Is this user a member of $partner (ignores manager flag)?
     */
    public function isAUserOfPartner(Partner $partner)
    {
        return $this->userPartners()
                    ->where('partner_id', '=', $partner->id)
                    ->wherePivot('approved', '=', 1)
                    ->get()
                    ->count()
                    ? true
                    : false;
    }

    /**
     * Does this user have an approved login for any partner?
     */
    public function isAnApprovedPartnerUser()
    {
        return $this->userPartners()
                    ->wherePivot('approved', '=', 1)
                    ->get()
                    ->count()
                    ? true
                    : false;
    }

    /**
     * Is this user a manager of any partners?
     */
    public function isAPartnerManager()
    {
        return $this->userPartners()
                    ->wherePivot('manager', '=', 1)
                    ->wherePivot('approved', '=', 1)
                    ->get()
                    ->count()
                    ? true
                    : false;
    }

    public function isPendingApprovalForPartner($partner)
    {
        return $this->userPartners()
                    ->where('partner_id', '=', $partner->id)
                    ->wherePivot('approved', '=', 0)
                    ->get()
                    ->count()
                    ? true
                    : false;
    }

    public function canLogin()
    {
        $runlevel = SystemVariable::where('variable_name', 'open_mode')->first()->variable_value;

        if ($this->id === 1) {
            return true;
        }

        switch ($runlevel) {
            case 0:
                return false;

            case 1:
                if ($this->hasPermissionTo('login at runlevel 1')) {
                    return true;
                }
                return false;

            case 2:
                return true;

            default:
                return false;
        }
    }
}
