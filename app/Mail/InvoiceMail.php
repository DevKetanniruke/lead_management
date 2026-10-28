<?php

namespace App\Mail;

use App\Models\Invoice;
use App\Services\Invoicing\InvoicePdf;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Attachment;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class InvoiceMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Invoice $invoice, public ?string $note = null) {}

    public function envelope(): Envelope
    {
        $seller = $this->invoice->seller();

        return new Envelope(
            subject: "Invoice {$this->invoice->number} from {$seller['name']}",
            replyTo: array_filter([$seller['email'] ?? null]),
        );
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.invoice', with: [
            'invoice' => $this->invoice,
            'seller' => $this->invoice->seller(),
            'note' => $this->note,
        ]);
    }

    public function attachments(): array
    {
        $pdf = app(InvoicePdf::class);

        return [
            Attachment::fromData(fn () => $pdf->make($this->invoice)->output(), $pdf->filename($this->invoice))->withMime('application/pdf'),
        ];
    }
}
