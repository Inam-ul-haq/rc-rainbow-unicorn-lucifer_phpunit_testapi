<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;

class SystemVariable extends Model
{
    use LogsActivity;

    protected static $logAttributes = ['*'];
    protected static $logName = 'system variable';
    protected static $logOnlyDirty = true;
}
