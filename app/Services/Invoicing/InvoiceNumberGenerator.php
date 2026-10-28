<?php

namespace App\Services\Invoicing;

use App\Models\InvoiceSequence;
use App\Support\Settings;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Generates invoice numbers such as INV-2026-0001 or INV-2026-27-0001.
 *
 * Must run inside the transaction that inserts the invoice: the series row is locked
 * (SELECT … FOR UPDATE), so concurrent requests queue up and each gets the next number.
 * Counters only ever go up, so numbers from deleted drafts are never reused, and
 * invoices.number is unique at the database level as a final guarantee.
 */
class InvoiceNumberGenerator
{
    public function next(CarbonInterface $invoiceDate): string
    {
        if (DB::transactionLevel() === 0) {
            return DB::transaction(fn () => $this->next($invoiceDate));
        }

        $series = $this->series($invoiceDate);
        $sequence = $this->lockSeries($series);

        $number = $sequence->next_number;
        $sequence->next_number = $number + 1;
        $sequence->save();

        return $series.'-'.str_pad((string) $number, max(1, (int) Settings::get('invoice_number_padding', 4)), '0', STR_PAD_LEFT);
    }

    /** Preview of the next number without consuming it (for the settings page). */
    public function preview(CarbonInterface $date): string
    {
        $series = $this->series($date);
        $next = InvoiceSequence::where('series', $series)->value('next_number') ?? 1;

        return $series.'-'.str_pad((string) $next, max(1, (int) Settings::get('invoice_number_padding', 4)), '0', STR_PAD_LEFT);
    }

    public function series(CarbonInterface $date): string
    {
        $prefix = strtoupper(trim((string) Settings::get('invoice_prefix', 'INV'))) ?: 'INV';

        return match (Settings::get('invoice_year_format', 'Y')) {
            'FY' => $prefix.'-'.$this->financialYear($date),
            'none' => $prefix,
            default => $prefix.'-'.$date->format('Y'),
        };
    }

    /** e.g. 2026-27 for any date from April 2026 to March 2027 (start month is configurable). */
    public function financialYear(CarbonInterface $date): string
    {
        $startMonth = (int) Settings::get('financial_year_start_month', 4);
        $startYear = $date->month >= $startMonth ? $date->year : $date->year - 1;

        return $startMonth === 1 ? (string) $startYear : $startYear.'-'.substr((string) ($startYear + 1), -2);
    }

    /**
     * Make sure the series row exists. Call this before opening the invoice transaction: locking a row
     * that does not exist yet takes gap locks on MySQL, which can deadlock two first-of-series requests.
     */
    public function prepare(CarbonInterface $invoiceDate): void
    {
        $this->ensureSeries($this->series($invoiceDate));
    }

    private function lockSeries(string $series): InvoiceSequence
    {
        return InvoiceSequence::where('series', $series)->lockForUpdate()->first()
            ?? $this->ensureSeries($series);
    }

    /** Idempotent: the unique index on `series` makes concurrent inserts collapse into one row. */
    private function ensureSeries(string $series): InvoiceSequence
    {
        InvoiceSequence::insertOrIgnore(['series' => $series, 'next_number' => 1, 'created_at' => now(), 'updated_at' => now()]);

        return InvoiceSequence::where('series', $series)->lockForUpdate()->firstOrFail();
    }
}
