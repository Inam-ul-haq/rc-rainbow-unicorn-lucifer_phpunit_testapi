<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;

class PartnerSalesRep extends Model
{
    use LogsActivity;

    protected static $logAttributes = ['*'];
    protected static $logName = 'partner sales rep';
    protected static $logOnlyDirty = true;

    public function partner()
    {
        return $this->belongsTo('App\Partner');
    }

    public function user()
    {
        return $this->belongsTo('App\User');
    }
}
