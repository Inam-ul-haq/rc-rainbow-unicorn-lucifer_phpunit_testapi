<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;

class AccountApplicationApproved extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $approve_message;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($approve_message = '')
    {
        $this->approve_message = $approve_message;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->view('email.standard-system-email')
                     ->with([
                         'header_text' => __('Emails.AccountApplicationApprovedHeader'),
                         'content_body' => __(
                             'Emails.AccountApplicationApprovedContent',
                             [
                                 'approve_message' => htmlspecialchars($this->approve_message),
                             ]
                         )
                     ]);
    }
}
