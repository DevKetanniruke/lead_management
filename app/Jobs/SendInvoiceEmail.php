<?php

namespace App\Jobs;

use App\Mail\InvoiceMail;
use App\Models\Invoice;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * Emails an invoice PDF. Runs outside any financial transaction and only ever touches the
 * informational `sent_at` column, so a mail failure can never alter amounts or status.
 */
class SendInvoiceEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [60, 300];

    /** @param list<string> $cc */
    public function __construct(
        public Invoice $invoice,
        public string $to,
        public array $cc,
        public ?string $message,
        public ?User $sentBy,
    ) {
        $this->afterCommit = true;
    }

    public function handle(): void
    {
        Mail::to($this->to)->cc($this->cc)->send(new InvoiceMail($this->invoice, $this->message));

        DB::table('invoices')->where('id', $this->invoice->id)->update(['sent_at' => now()]);
    }

    public function failed(\Throwable $e): void
    {
        Audit::log('invoice.email_failed', $this->invoice, "Emailing invoice {$this->invoice->number} to {$this->to} failed", [
            'error' => mb_substr($e->getMessage(), 0, 300),
        ]);
    }
}
