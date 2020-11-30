<?php

namespace App;

use App\Traits\Uuids;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;

class Tag extends Model
{
    use Uuids;
    use LogsActivity;

    protected static $logAttributes = ['*'];
    protected static $logName = 'tag';
    protected static $logOnlyDirty = true;

    public function vouchers()
    {
        return $this->belongsToMany('App\Voucher', 'voucher_tag')
                    ->withPivot('when_to_subscribe');  ## FK6, FK7
    }

    public function consumers()
    {
        return $this->belongsToMany('App\Consumer', 'consumer_tag')
                    ->withTimestamps(); ## FK51, FK52
    }
}
