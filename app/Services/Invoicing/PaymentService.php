<?php

namespace App\Services\Invoicing;

use App\Exceptions\InvoiceStateException;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\User;
use App\Notifications\PaymentRecorded;
use App\Support\Audit;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

/**
 * Records, reverses and corrects payments. The invoice row is locked while its balance changes,
 * amounts are compared with decimal arithmetic, and the invoice's amount_paid / balance_due are
 * always recomputed from the allocation ledger rather than incremented, so they cannot drift.
 *
 *   Outstanding balance = invoice grand total − Σ allocations of completed (non-reversed) payments
 */
class PaymentService
{
    /** @param array{amount: mixed, payment_date: string, method: string, reference?: ?string, notes?: ?string} $data */
    public function record(Invoice $invoice, array $data, User $by, ?Payment $corrects = null): Payment
    {
        $payment = DB::transaction(function () use ($invoice, $data, $by, $corrects) {
            $locked = Invoice::whereKey($invoice->getKey())->lockForUpdate()->firstOrFail();
            $amount = Money::round($data['amount']);

            if (! $locked->isIssued()) {
                throw new InvoiceStateException('Payments can only be recorded against issued invoices.');
            }
            if (! Money::isPositive($amount)) {
                throw new InvoiceStateException('The payment amount must be greater than zero.');
            }
            if (Money::cmp($amount, $locked->balance_due) > 0) {
                throw new InvoiceStateException('The payment of '.money($amount, $locked->currency).' exceeds the outstanding balance of '.money($locked->balance_due, $locked->currency).'.');
            }

            $payment = new Payment;
            $payment->forceFill([
                'client_id' => $locked->client_id,
                'amount' => $amount,
                'currency' => $locked->currency,
                'method' => $data['method'],
                'reference' => $data['reference'] ?? null,
                'payment_date' => $data['payment_date'],
                'notes' => $data['notes'] ?? null,
                'status' => Payment::COMPLETED,
                'recorded_by' => $by->id,
                'corrects_payment_id' => $corrects?->id,
            ])->save();

            $allocation = new PaymentAllocation;
            $allocation->forceFill(['payment_id' => $payment->id, 'invoice_id' => $locked->id, 'amount' => $amount])->save();

            $this->recalculate($locked);

            Audit::log($corrects ? 'payment.corrected' : 'payment.recorded', $payment,
                ($corrects ? "Correction for {$corrects->receiptNumber()}: " : '')."Payment {$payment->receiptNumber()} of ".money($amount, $locked->currency)." recorded against {$locked->number}",
                [
                    'invoice_id' => $locked->id,
                    'amount' => $amount,
                    'method' => $payment->method,
                    'balance_due_after' => (string) $locked->balance_due,
                    'corrects_payment_id' => $corrects?->id,
                ]);

            return $payment;
        });

        $this->notifyRecorded($payment, $invoice->fresh(['client.accountManager']), $by);

        return $payment;
    }

    /** Cancel a recorded payment (kept on record) and restore the invoice balance. */
    public function reverse(Payment $payment, string $reason, User $by): Payment
    {
        return DB::transaction(function () use ($payment, $reason, $by) {
            $locked = Payment::whereKey($payment->getKey())->lockForUpdate()->firstOrFail();
            if (! $locked->isCompleted()) {
                throw new InvoiceStateException('This payment has already been reversed.');
            }

            $invoices = Invoice::whereIn('id', $locked->allocations()->pluck('invoice_id'))->lockForUpdate()->get();

            $locked->forceFill([
                'status' => Payment::REVERSED,
                'reversed_at' => now(),
                'reversed_by' => $by->id,
                'reversal_reason' => $reason,
            ])->save();

            $invoices->each(fn (Invoice $i) => $this->recalculate($i));

            Audit::log('payment.reversed', $locked, "Payment {$locked->receiptNumber()} of ".money($locked->amount, $locked->currency)." reversed: $reason", [
                'amount' => (string) $locked->amount,
                'invoice_ids' => $invoices->pluck('id')->all(),
                'reason' => $reason,
            ]);

            return $locked;
        });
    }

    /** Authorised correction: reverse the original and record the corrected payment, atomically. */
    public function correct(Payment $payment, array $data, string $reason, User $by): Payment
    {
        return DB::transaction(function () use ($payment, $data, $reason, $by) {
            $invoice = $payment->allocations()->firstOrFail()->invoice;
            $reversed = $this->reverse($payment, 'Corrected: '.$reason, $by);

            return $this->record($invoice, $data, $by, $reversed);
        });
    }

    /** Recompute the cached payment figures from the allocation ledger. Call with the invoice row locked. */
    public function recalculate(Invoice $invoice): Invoice
    {
        $paid = Money::sum(
            PaymentAllocation::query()
                ->where('invoice_id', $invoice->id)
                ->whereHas('payment', fn ($q) => $q->where('status', Payment::COMPLETED))
                ->pluck('amount')
        );

        $balance = $invoice->isVoid() ? '0.00' : Money::max(Money::sub($invoice->grand_total, $paid), '0');
        $fullyPaid = $invoice->isIssued() && ! Money::isPositive($balance);

        $invoice->forceFill([
            'amount_paid' => $paid,
            'balance_due' => $balance,
            'paid_at' => $fullyPaid ? ($invoice->paid_at ?? now()) : null,
        ])->save();

        return $invoice;
    }

    private function notifyRecorded(Payment $payment, Invoice $invoice, User $by): void
    {
        $manager = $invoice->client?->accountManager;

        if ($manager && $manager->id !== $by->id && $manager->is_active) {
            try {
                $manager->notify(new PaymentRecorded($payment, $invoice));
            } catch (\Throwable $e) {
                report($e); // never undo a recorded payment because a notification failed
            }
        }
    }
}
