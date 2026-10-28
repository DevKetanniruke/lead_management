<?php

namespace App\Notifications;

use App\Models\Invoice;
use App\Notifications\Concerns\CrmNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class InvoiceOverdue extends Notification implements ShouldQueue
{
    use CrmNotification, Queueable;

    public const PREFERENCE = 'invoice_overdue';

    public function __construct(public Invoice $invoice) {}

    public function toMail(object $notifiable): MailMessage
    {
        $i = $this->invoice;

        return (new MailMessage)
            ->subject("Overdue: invoice {$i->number} ({$i->client->displayName()})")
            ->greeting("Hi {$notifiable->name},")
            ->line("Invoice {$i->number} was due on {$i->due_date->format('M j, Y')} and ".money($i->balance_due, $i->currency).' is still outstanding.')
            ->action('View invoice', route('invoices.show', $i));
    }

    protected function payload(object $notifiable): array
    {
        $i = $this->invoice;

        return [
            'title' => "Invoice {$i->number} is overdue",
            'body' => $i->client->displayName().' · '.money($i->balance_due, $i->currency).' outstanding since '.$i->due_date->format('M j'),
            'url' => route('invoices.show', $i),
        ];
    }
}
