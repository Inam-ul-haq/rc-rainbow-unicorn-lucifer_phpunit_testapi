<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;

class VoucherCoupon extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $subject;
    public $email_html;

    public function __construct(string $subject, string $email_html)
    {
        $this->subject = $subject;
        $this->email_html = $email_html;
    }

    public function build()
    {
        return $this->subject($this->subject)
                    ->view('email.flexible_content')
                    ->with([
                        'email_html' => $this->email_html,
                        ]);
    }
}
