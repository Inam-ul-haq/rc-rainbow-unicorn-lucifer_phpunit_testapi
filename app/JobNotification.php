<?php

namespace App;

use URL;
use Storage;
use App\Traits\Uuids;
use App\Helpers\Helper;
use Illuminate\Database\Eloquent\Model;

class JobNotification extends Model
{
    use Uuids;
    protected static $logAttributes = ['*'];
    protected static $logName = 'job notification';
    protected static $logOnlyDirty = true;

    protected $hidden = [
        'id',
        'filename',
        'user_id',
        'access_url',
        'downloadkey',
        'disk',
        'download_count',
        'download_limit',
    ];
    protected $appends = [ 'access_url' ];

    public function user()
    {
        return $this->belongsTo('App\User');
    }

    public function getAccessUrlAttribute()
    {
        return URL::to('files/' . $this->downloadkey);
    }

    public function canStillBeDownloaded()
    {
        if ($this->download_limit === 0) {
            return true;
        }

        if ($this->download_count < $this->download_limit) {
            return true;
        }

        return false;
    }

    public function downloadFilename()
    {
        return $this->type . '_' . $this->id . '.' . Helper::getFileExtension($this->filepath());
    }

    public function outputDownloadHeaders()
    {
        header('Content-Length: ' . filesize($this->filepath()));
        header('Content-Disposition: attachment; filename="' . $this->downloadFilename() . '"');
        switch (Helper::getFileExtension($this->filepath())) {
            case 'csv':
                header('Content-type: application/csv;');
                break;
            case 'zip':
                header('Content-type: application/zip;');
                break;
        }
    }

    private function filepath()
    {
        return Storage::disk($this->disk)->path($this->filename);
    }
}
