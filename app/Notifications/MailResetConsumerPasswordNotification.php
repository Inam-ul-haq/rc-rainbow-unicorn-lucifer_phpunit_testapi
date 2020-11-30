<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Auth\Notifications\ResetPassword;

class MailResetConsumerPasswordNotification extends ResetPassword implements ShouldQueue
{
    use Queueable;
    public $token;
    public $reset_url;
    public $email;

    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct(array $variables)
    {
        $this->token = $variables['token'];
        $this->reset_url = $variables['reset_url'];
        $this->email = $variables['email'];
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function via($notifiable)
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return \Illuminate\Notifications\Messages\MailMessage
     */
    public function toMail($notifiable)
    {
        return (new MailMessage)
            ->subject('Reset Consumer Password Notification')
            ->view('email.users.account_password_reset', [
                'reset_url' => $this->reset_url,
                'token' => $this->token,
                'email' => $this->email
            ]);
    }

    /**
     * Get the array representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function toArray($notifiable)
    {
        return [
            //
        ];
    }
}
