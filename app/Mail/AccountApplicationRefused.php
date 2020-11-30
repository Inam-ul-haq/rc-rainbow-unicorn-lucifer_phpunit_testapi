<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;

class AccountApplicationRefused extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $reject_reason;
    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($reject_reason = '')
    {
        $this->reject_reason = $reject_reason;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->view('email.standard-system-email')
                    ->with(
                        [
                            'header_text' => __('Emails.AccountApplicationRejectedHeader'),
                            'content_body' => __(
                                'Emails.AccountApplicationRejectedContent',
                                [
                                    'reject_message' => htmlspecialchars($this->reject_reason),
                                ]
                            )
                        ]
                    );
    }
}
