<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;

class NameTitle extends Model
{
    use LogsActivity;

    protected static $logAttributes = ['*'];
    protected static $logName = 'name title';
    protected static $logOnlyDirty = true;

    public function consumers()
    {
        return $this->hasMany('App\Consumer'); ## FK9
    }

    public function partners()
    {
        return $this->hasMany('App\Partner'); ## FK30
    }

    public function referrers()
    {
        return $this->hasMany('App\Referrer'); ## FK19
    }
}
