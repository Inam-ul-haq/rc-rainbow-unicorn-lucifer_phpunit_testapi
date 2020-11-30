<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Auth\Notifications\ResetPassword;

//class MailResetPasswordNotification extends Notification
class MailResetPasswordNotification extends ResetPassword
{
    use Queueable;

    public $token;
    public $reset_url;

    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct(array $variables)
    {
        $this->token = $variables['token'];
        $this->email = $variables['email'];
        $this->reset_url = $variables['reset_url'];
        //
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
            ->subject('Reset Internal User Password Notification')
            ->view('reset', [
                'reset_url' => $this->reset_url,
                'token' => $this->token,
                'email' => $this->email
            ]);
            //->line('You are receiving this email because we received a password reset request for your account.')
            //->action('Reset Password', $this->reset_url . $this->token . '&email=' . $this->email )
            //->line('If you did not request a password reset, no further action is required.');
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
