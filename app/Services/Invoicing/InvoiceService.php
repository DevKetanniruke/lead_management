<?php

namespace App\Services\Invoicing;

use App\Exceptions\InvoiceStateException;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\User;
use App\Notifications\InvoiceIssued;
use App\Support\Audit;
use App\Support\Money;
use App\Support\Settings;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * The only place invoice financials are written. Drafts can be edited freely; once issued the
 * financial details are frozen and changes go through void-and-reissue. Every step is audited.
 */
class InvoiceService
{
    public function __construct(
        private InvoiceCalculator $calculator,
        private InvoiceNumberGenerator $numbers,
    ) {}

    /**
     * @param  array<string, mixed>  $data  header fields (client_id, invoice_date, due_date, …)
     * @param  list<array<string, mixed>>  $items
     */
    public function createDraft(array $data, array $items, User $by, array $links = []): Invoice
    {
        $date = Carbon::parse($data['invoice_date']);
        $this->numbers->prepare($date);

        $invoice = DB::transaction(function () use ($data, $items, $by, $date, $links) {
            $invoice = new Invoice($data);
            $invoice->forceFill([
                'number' => $this->numbers->next($date),
                'status' => Invoice::DRAFT,
                'created_by' => $by->id,
                'duplicated_from_id' => $links['duplicated_from_id'] ?? null,
                'amends_invoice_id' => $links['amends_invoice_id'] ?? null,
            ]);
            $this->applyItems($invoice, $items);

            Audit::log('invoice.created', $invoice, "Draft invoice {$invoice->number} created for {$invoice->client->displayName()}", [
                'grand_total' => (string) $invoice->grand_total,
                'currency' => $invoice->currency,
            ]);

            return $invoice;
        }, 3);

        return $invoice->load('items');
    }

    public function updateDraft(Invoice $invoice, array $data, array $items, User $by): Invoice
    {
        return DB::transaction(function () use ($invoice, $data, $items) {
            $locked = $this->lock($invoice);
            $this->assertState($locked, Invoice::DRAFT, 'Only draft invoices can be edited. Void and reissue an issued invoice instead.');

            $before = (string) $locked->grand_total;
            $locked->fill($data);
            $this->applyItems($locked, $items);

            Audit::log('invoice.updated', $locked, "Draft invoice {$locked->number} updated", [
                'grand_total_before' => $before,
                'grand_total' => (string) $locked->grand_total,
            ]);

            return $locked->load('items');
        });
    }

    /** Freeze details, recalculate once more and make the invoice a receivable. */
    public function issue(Invoice $invoice, User $by): Invoice
    {
        $issued = DB::transaction(function () use ($invoice, $by) {
            $locked = $this->lock($invoice);
            $this->assertState($locked, Invoice::DRAFT, 'This invoice has already been issued or voided.');

            $locked->load('items', 'client');
            if ($locked->items->isEmpty()) {
                throw new InvoiceStateException('Add at least one line item before issuing.');
            }

            // Recalculate with the current tax context so the frozen figures are consistent.
            $this->applyItems($locked, $locked->items->map->only(['description', 'hsn_sac', 'quantity', 'unit_price', 'discount', 'tax_rate'])->all());

            if (! Money::isPositive($locked->grand_total)) {
                throw new InvoiceStateException('The invoice total must be greater than zero.');
            }

            $locked->forceFill([
                'status' => Invoice::ISSUED,
                'issued_at' => now(),
                'issued_by' => $by->id,
                'seller_details' => Invoice::currentSellerDetails(),
                'billing_details' => Invoice::billingDetailsFor($locked->client),
                'amount_paid' => '0.00',
                'balance_due' => $locked->grand_total,
            ])->save();

            Audit::log('invoice.issued', $locked, "Invoice {$locked->number} issued for ".money($locked->grand_total, $locked->currency), [
                'grand_total' => (string) $locked->grand_total,
                'tax_total' => (string) $locked->tax_total,
                'due_date' => $locked->due_date->toDateString(),
            ]);

            return $locked;
        });

        $this->notifyIssued($issued, $by);

        return $issued;
    }

    /** Cancel an issued invoice. Payments must be reversed first so no money is left unexplained. */
    public function void(Invoice $invoice, string $reason, User $by): Invoice
    {
        return DB::transaction(function () use ($invoice, $reason, $by) {
            $locked = $this->lock($invoice);
            $this->assertState($locked, Invoice::ISSUED, 'Only issued invoices can be voided. Delete a draft instead.');

            if (Money::isPositive($locked->amount_paid)) {
                throw new InvoiceStateException('This invoice has recorded payments. Reverse them before voiding.');
            }

            $locked->forceFill([
                'status' => Invoice::VOID,
                'voided_at' => now(),
                'voided_by' => $by->id,
                'void_reason' => $reason,
                'balance_due' => '0.00',
            ])->save();

            Audit::log('invoice.voided', $locked, "Invoice {$locked->number} voided: $reason", [
                'grand_total' => (string) $locked->grand_total,
                'reason' => $reason,
            ]);

            return $locked;
        });
    }

    /** Copy an invoice into a new draft dated today (new number). */
    public function duplicate(Invoice $source, User $by, array $links = []): Invoice
    {
        $source->loadMissing('items', 'client');
        $today = today();
        // Keep the original payment window (due date − invoice date).
        $terms = max(0, (int) $source->invoice_date->diffInDays($source->due_date, false));

        $copy = $this->createDraft([
            'client_id' => $source->client_id,
            'lead_id' => $source->lead_id,
            'invoice_date' => $today->toDateString(),
            'due_date' => $today->copy()->addDays((int) $terms)->toDateString(),
            'currency' => $source->currency,
            'place_of_supply' => $source->client->state_code ?? $source->place_of_supply,
            'payment_terms' => $source->payment_terms,
            'notes' => $source->notes,
            'internal_notes' => $source->internal_notes,
            'terms' => $source->terms,
        ], $source->items->map->only(['description', 'hsn_sac', 'quantity', 'unit_price', 'discount', 'tax_rate'])->all(), $by, $links + ['duplicated_from_id' => $source->id]);

        Audit::log('invoice.duplicated', $copy, "Invoice {$source->number} duplicated as draft {$copy->number}", ['source_id' => $source->id]);

        return $copy;
    }

    /** Controlled amendment: void the issued invoice and open a corrected draft linked to it. */
    public function voidAndReissue(Invoice $invoice, string $reason, User $by): Invoice
    {
        return DB::transaction(function () use ($invoice, $reason, $by) {
            $voided = $this->void($invoice, $reason, $by);

            return $this->duplicate($voided, $by, ['amends_invoice_id' => $voided->id]);
        });
    }

    /** Drafts only. Soft-deleted so the number stays taken and is never reused. */
    public function deleteDraft(Invoice $invoice, User $by): void
    {
        DB::transaction(function () use ($invoice) {
            $locked = $this->lock($invoice);
            $this->assertState($locked, Invoice::DRAFT, 'Only drafts can be deleted. Void an issued invoice instead.');

            Audit::log('invoice.deleted', $locked, "Draft invoice {$locked->number} deleted", ['grand_total' => (string) $locked->grand_total]);
            $locked->delete();
        });
    }

    /** Tax treatment for a client/place of supply under the current settings. */
    public function taxTypeFor(?string $placeOfSupply): string
    {
        return InvoiceCalculator::taxTypeFor(
            (string) Settings::get('tax_system', 'gst'),
            Settings::get('company_state_code'),
            $placeOfSupply,
        );
    }

    /** Recalculate and replace the line items, then store the totals. */
    private function applyItems(Invoice $invoice, array $items): void
    {
        $system = (string) Settings::get('tax_system', 'gst');
        $taxType = InvoiceCalculator::taxTypeFor($system, Settings::get('company_state_code'), $invoice->place_of_supply);
        $result = $this->calculator->calculate($items, $taxType);

        $invoice->forceFill($result['totals'] + [
            'tax_system' => $system,
            'tax_type' => $taxType,
            'balance_due' => $invoice->isIssued() ? $invoice->balance_due : $result['totals']['grand_total'],
        ])->save();

        $invoice->items()->delete();
        foreach ($result['items'] as $i => $line) {
            $invoice->items()->create($line + [
                'position' => $i + 1,
                'description' => $items[$i]['description'],
                'hsn_sac' => $items[$i]['hsn_sac'] ?? null,
            ]);
        }
        $invoice->unsetRelation('items');
    }

    private function lock(Invoice $invoice): Invoice
    {
        return Invoice::whereKey($invoice->getKey())->lockForUpdate()->firstOrFail();
    }

    private function assertState(Invoice $invoice, string $state, string $message): void
    {
        if ($invoice->status !== $state) {
            throw new InvoiceStateException($message);
        }
    }

    private function notifyIssued(Invoice $invoice, User $by): void
    {
        $manager = $invoice->client->accountManager;

        if ($manager && $manager->id !== $by->id && $manager->is_active) {
            try {
                $manager->notify(new InvoiceIssued($invoice));
            } catch (\Throwable $e) {
                report($e); // never undo an issued invoice because a notification failed
            }
        }
    }
}
