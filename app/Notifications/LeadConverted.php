<?php

namespace App\Notifications;

use App\Models\Client;
use App\Models\Lead;
use App\Models\User;
use App\Notifications\Concerns\CrmNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LeadConverted extends Notification implements ShouldQueue
{
    use CrmNotification, Queueable;

    public const PREFERENCE = 'lead_converted';

    public function __construct(public Lead $lead, public Client $client, public User $convertedBy) {}

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Lead converted: {$this->client->displayName()}")
            ->greeting("Hi {$notifiable->name},")
            ->line("{$this->convertedBy->name} converted the lead \"{$this->lead->name}\" into the client {$this->client->displayName()}.")
            ->action('Open client', route('clients.show', $this->client));
    }

    protected function payload(object $notifiable): array
    {
        return [
            'title' => 'Lead converted to client',
            'body' => "{$this->lead->name} → {$this->client->displayName()} (by {$this->convertedBy->name})",
            'url' => route('clients.show', $this->client),
        ];
    }
}
