<?php

namespace App\Notifications\Concerns;

/**
 * Shared behaviour for CRM notifications: in-app always, email unless the user opted out,
 * dispatched only after the surrounding DB transaction commits, retried on failure.
 *
 * Using classes define PREFERENCE (a User::NOTIFICATION_TYPES key) and payload().
 */
trait CrmNotification
{
    public int $tries = 3;

    public array $backoff = [60, 300];

    public function via(object $notifiable): array
    {
        $this->afterCommit = true;

        $channels = ['database'];

        if (! method_exists($notifiable, 'wantsMail') || $notifiable->wantsMail(static::PREFERENCE)) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    /** @return array{title: string, body: string, url: string|null} */
    abstract protected function payload(object $notifiable): array;

    public function toArray(object $notifiable): array
    {
        return $this->payload($notifiable);
    }
}
