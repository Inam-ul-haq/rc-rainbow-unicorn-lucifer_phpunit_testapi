<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;

class ReferrerPoints extends Model
{
    use LogsActivity;

    public function referrer()
    {
        return $this->belongsTo('App\Referrer');
    }

    public function consumer()
    {
        return $this->belongsTo('App\Consumer');
    }

    public function coupon()
    {
        return $this->belongsTo('App\Coupon');
    }
}
