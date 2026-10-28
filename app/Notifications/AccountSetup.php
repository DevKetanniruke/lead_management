<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Welcome email with a one-time link to choose a password (uses the password broker token). */
class AccountSetup extends Notification
{
    use Queueable;

    public function __construct(public string $token) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = route('password.reset', ['token' => $this->token, 'email' => $notifiable->getEmailForPasswordReset()]);
        $minutes = config('auth.passwords.'.config('auth.defaults.passwords').'.expire');

        return (new MailMessage)
            ->subject('Set up your '.config('app.name').' account')
            ->greeting("Welcome, {$notifiable->name}!")
            ->line('An account has been created for you. Choose a password to get started.')
            ->action('Set your password', $url)
            ->line("This link expires in $minutes minutes. Ask your administrator for a new one if it expires.");
    }
}
