<?php

namespace App\Helpers;

use Auth\User;
use App\JobNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Spatie\PersonalDataExport\Jobs\CreatePersonalDataExportJob;
use Spatie\PersonalDataExport\Zip;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Contracts\Filesystem\Filesystem;
use Spatie\TemporaryDirectory\TemporaryDirectory as TemporaryDirectory;
use Spatie\PersonalDataExport\ExportsPersonalData;
use Spatie\PersonalDataExport\PersonalDataSelection;
use Spatie\PersonalDataExport\Exceptions\InvalidUser;
use Spatie\PersonalDataExport\Events\PersonalDataSelected;
use Spatie\PersonalDataExport\Events\PersonalDataExportCreated;

class NotifyPersonalDataExport extends CreatePersonalDataExportJob implements ShouldQueue
{

    protected $notification;

    public function __construct(ExportsPersonalData $user, JobNotification $notification)
    {
        $this->ensureValidUser($user);

        $this->user = $user;
        $this->notification = $notification;
    }

    public function handle()
    {
        $temporaryDirectory = (new TemporaryDirectory())->create();

        $personalDataSelection = $this->selectPersonalData($temporaryDirectory);

        event(new PersonalDataSelected($personalDataSelection, $this->user));

        $zipFilename = $this->zipPersonalData($personalDataSelection, $this->getDisk(), $temporaryDirectory);

        $this->notification->filename = $zipFilename;
        $this->notification->disk = config('personal-data-export.disk');
        $this->notification->status = 'available';
        $this->notification->downloadkey = md5(uniqid($this->notification->user_id, true));
        $this->notification->save();

        $temporaryDirectory->delete();


        event(new PersonalDataExportCreated($zipFilename, $this->user));
    }
}
