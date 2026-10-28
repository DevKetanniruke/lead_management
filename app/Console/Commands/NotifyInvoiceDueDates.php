<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use App\Notifications\InvoiceDueSoon;
use App\Notifications\InvoiceOverdue;
use App\Support\Settings;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Reminds account managers about receivables approaching their due date and those that just became
 * overdue. Each invoice triggers each reminder at most once; drafts, voids and paid invoices never do.
 */
class NotifyInvoiceDueDates extends Command
{
    protected $signature = 'invoices:notify-due';

    protected $description = 'Notify account managers about invoices due soon and newly overdue invoices (once each)';

    public function handle(): int
    {
        $days = (int) Settings::get('due_reminder_days', 3);

        $dueSoon = $days > 0
            ? $this->notify(
                Invoice::receivable()->whereNull('due_reminder_sent_at')
                    ->whereDate('due_date', '>=', today())->whereDate('due_date', '<=', today()->addDays($days)),
                'due_reminder_sent_at',
                fn (Invoice $i) => new InvoiceDueSoon($i),
            )
            : 0;

        $overdue = $this->notify(
            Invoice::overdue()->whereNull('overdue_notified_at'),
            'overdue_notified_at',
            fn (Invoice $i) => new InvoiceOverdue($i),
        );

        $this->info("Sent $dueSoon due-soon and $overdue overdue reminder(s).");

        return self::SUCCESS;
    }

    private function notify($query, string $markColumn, callable $make): int
    {
        $sent = 0;

        $query->with('client.accountManager')->orderBy('id')->chunkById(200, function ($invoices) use ($markColumn, $make, &$sent) {
            foreach ($invoices as $invoice) {
                $manager = $invoice->client?->accountManager;

                if ($manager?->is_active) {
                    try {
                        $manager->notify($make($invoice));
                        $sent++;
                    } catch (\Throwable $e) {
                        report($e);

                        continue; // not marked: retried on the next run
                    }
                }

                // Mark without touching updated_at or any financial column.
                DB::table('invoices')->where('id', $invoice->id)->update([$markColumn => now()]);
            }
        });

        return $sent;
    }
}
