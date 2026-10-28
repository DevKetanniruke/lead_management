<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Always emailed: account access changes are not optional. */
class AccountStatusChanged extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public bool $active)
    {
        $this->afterCommit = true;
    }

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject($this->active ? 'Your account has been activated' : 'Your account has been deactivated')
            ->greeting("Hi {$notifiable->name},");

        return $this->active
            ? $mail->line('Your '.config('app.name').' account is active. You can sign in again.')->action('Sign in', route('login'))
            : $mail->line('Your '.config('app.name').' account has been deactivated. Contact your administrator if you think this is a mistake.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->active ? 'Account activated' : 'Account deactivated',
            'body' => $this->active ? 'Your account is active again.' : 'Your account has been deactivated.',
            'url' => null,
        ];
    }
}
