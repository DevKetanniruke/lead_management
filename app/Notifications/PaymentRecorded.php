<?php

namespace App\Notifications;

use App\Models\Invoice;
use App\Models\Payment;
use App\Notifications\Concerns\CrmNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PaymentRecorded extends Notification implements ShouldQueue
{
    use CrmNotification, Queueable;

    public const PREFERENCE = 'payment_recorded';

    public function __construct(public Payment $payment, public Invoice $invoice) {}

    public function toMail(object $notifiable): MailMessage
    {
        $p = $this->payment;
        $i = $this->invoice;

        return (new MailMessage)
            ->subject('Payment received: '.money($p->amount, $p->currency)." for {$i->number}")
            ->greeting("Hi {$notifiable->name},")
            ->line(money($p->amount, $p->currency)." was received from {$i->client->displayName()} by {$p->method} on {$p->payment_date->format('M j, Y')}.")
            ->line('Remaining balance on '.$i->number.': '.money($i->balance_due, $i->currency))
            ->action('View invoice', route('invoices.show', $i));
    }

    protected function payload(object $notifiable): array
    {
        $p = $this->payment;
        $i = $this->invoice;

        return [
            'title' => 'Payment received for '.$i->number,
            'body' => money($p->amount, $p->currency).' from '.$i->client->displayName().' · balance '.money($i->balance_due, $i->currency),
            'url' => route('invoices.show', $i),
        ];
    }
}
