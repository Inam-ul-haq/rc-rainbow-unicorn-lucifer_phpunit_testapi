<?php

use Spatie\Activitylog\Models\Activity;

function setLogLabel(Activity $activity, $label = '')
{
    $activity->log_label = $label;
}
