<?php

namespace App\Notifications;

use App\Models\User;
use App\Notifications\Concerns\CrmNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;

class LeadAssigned extends Notification implements ShouldQueue
{
    use CrmNotification, Queueable;

    public const PREFERENCE = 'lead_assigned';

    /** @param Collection<int, \App\Models\Lead> $leads */
    public function __construct(public Collection $leads, public ?User $assignedBy = null) {}

    public function toMail(object $notifiable): MailMessage
    {
        $count = $this->leads->count();
        $mail = (new MailMessage)
            ->subject($count === 1 ? 'New lead assigned: '.$this->leads->first()->name : "$count new leads assigned to you")
            ->greeting("Hi {$notifiable->name},")
            ->line(($this->assignedBy ? $this->assignedBy->name.' assigned' : 'You were assigned').($count === 1 ? ' a lead to you.' : " $count leads to you."));

        foreach ($this->leads->take(10) as $lead) {
            $mail->line("• {$lead->name}".($lead->company ? " ({$lead->company})" : ''));
        }

        return $mail->action($count === 1 ? 'View lead' : 'View leads', $this->url());
    }

    protected function payload(object $notifiable): array
    {
        $count = $this->leads->count();

        return [
            'title' => $count === 1 ? 'Lead assigned to you' : "$count leads assigned to you",
            'body' => $this->leads->take(3)->pluck('name')->implode(', ').($count > 3 ? '…' : ''),
            'url' => $this->url(),
        ];
    }

    private function url(): string
    {
        return $this->leads->count() === 1 ? route('leads.show', $this->leads->first()) : route('leads.index');
    }
}
