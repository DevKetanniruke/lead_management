<?php

namespace App\Notifications;

use App\Models\Invoice;
use App\Notifications\Concerns\CrmNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class InvoiceDueSoon extends Notification implements ShouldQueue
{
    use CrmNotification, Queueable;

    public const PREFERENCE = 'invoice_due';

    public function __construct(public Invoice $invoice) {}

    public function toMail(object $notifiable): MailMessage
    {
        $i = $this->invoice;

        return (new MailMessage)
            ->subject("Invoice {$i->number} is due {$this->when()}")
            ->greeting("Hi {$notifiable->name},")
            ->line("{$i->client->displayName()} has ".money($i->balance_due, $i->currency)." outstanding on invoice {$i->number}, due {$i->due_date->format('M j, Y')}.")
            ->action('View invoice', route('invoices.show', $i));
    }

    protected function payload(object $notifiable): array
    {
        $i = $this->invoice;

        return [
            'title' => "Invoice {$i->number} due {$this->when()}",
            'body' => $i->client->displayName().' · '.money($i->balance_due, $i->currency).' outstanding',
            'url' => route('invoices.show', $i),
        ];
    }

    private function when(): string
    {
        return $this->invoice->due_date->isToday() ? 'today' : 'on '.$this->invoice->due_date->format('M j');
    }
}
