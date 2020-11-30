<?php

namespace App\Http\Controllers;

use Storage;
use App\JobNotification;
use Illuminate\Http\Request;

class JobNotificationController extends Controller
{
    public function show(JobNotification $notification)
    {
        if (stristr($notification->status, 'available')) {
            return $notification->makeVisible('access_url');
        }

        return $notification;
    }

    public static function downloadJobFile($download_key = null)
    {
        if ($download_key == null) {
            return response()->json(['error' => __('Generic.not_found')], 404);
        }
        $job = JobNotification::where('downloadkey', $download_key)->first();
        if (!$job) {
            return response()->json(['error' => __('Generic.not_found')], 404);
        }

        if (!$job->canStillBeDownloaded()) {
            return response()->json(['error' => __('Generic.not_found')], 404);
        }

        $job->download_count++;
        $job->status = 'downloaded, available';

        $job->outputDownloadHeaders();

        $file = Storage::disk($job->disk)->path($job->filename);
        readfile($file);

        activity('background jobs')
            ->on($job)
            ->tap('setLogLabel', 'file downloaded')
            ->log('Job file downloaded');

        if (!$job->canStillBeDownloaded()) {
            $job->downloadkey = null;
            $job->status = 'download limit reached';
            Storage::disk($job->disk)->delete($job->filename);
        }

        $job->save();
    }
}
