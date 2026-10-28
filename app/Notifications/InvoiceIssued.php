<?php

namespace App\Notifications;

use App\Models\Invoice;
use App\Notifications\Concerns\CrmNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class InvoiceIssued extends Notification implements ShouldQueue
{
    use CrmNotification, Queueable;

    public const PREFERENCE = 'invoice_issued';

    public function __construct(public Invoice $invoice) {}

    public function toMail(object $notifiable): MailMessage
    {
        $i = $this->invoice;

        return (new MailMessage)
            ->subject("Invoice {$i->number} issued to {$i->client->displayName()}")
            ->greeting("Hi {$notifiable->name},")
            ->line("Invoice {$i->number} for ".money($i->grand_total, $i->currency)." was issued to {$i->client->displayName()}.")
            ->line('Due date: '.$i->due_date->format('M j, Y'))
            ->action('View invoice', route('invoices.show', $i));
    }

    protected function payload(object $notifiable): array
    {
        $i = $this->invoice;

        return [
            'title' => "Invoice {$i->number} issued",
            'body' => $i->client->displayName().' · '.money($i->grand_total, $i->currency).' · due '.$i->due_date->format('M j'),
            'url' => route('invoices.show', $i),
        ];
    }
}
