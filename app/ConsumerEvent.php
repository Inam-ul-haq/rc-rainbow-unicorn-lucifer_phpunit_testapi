<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ConsumerEvent extends Model
{
    use SoftDeletes;

    public $updated_at = null;
    const UPDATED_AT=null;

    protected $fillable = [
        'event',
        'properties',
        'consumer_id',
        'event_source',
        'created_at'
    ];

    public function consumer()
    {
        return $this->belongsTo('App\Consumer');
    }
}
