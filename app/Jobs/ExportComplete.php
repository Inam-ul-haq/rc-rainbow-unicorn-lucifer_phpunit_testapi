<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\JobNotification;

class ExportComplete implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 1;  // Only need to try this once. If we can't update an object,
                        // then something's really wrong (as it was when I was writing
                        // this comment, and this job was trying to run 255 times!).

    private $notification;

    public function __construct(JobNotification $notification)
    {
        $this->notification = $notification;
    }

    public function handle()
    {
        $this->notification->status = 'available';
        $this->notification->downloadkey = md5(uniqid($this->notification->user_id, true));
        $this->notification->save();
    }
}
