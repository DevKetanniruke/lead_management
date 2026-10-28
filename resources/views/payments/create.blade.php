@php $input = 'mt-1 block w-full border-slate-300 rounded-lg shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500'; @endphp

<x-app-layout title="Record payment">
    <x-slot name="header">
        <h2 class="truncate text-lg font-semibold text-slate-900">Record payment</h2>
    </x-slot>

    <form method="POST" action="{{ route('payments.store') }}" class="mx-auto max-w-3xl space-y-6"
          x-data="{ amount: @js(old('amount', $invoice ? (string) $invoice->balance_due : '')), balance: @js($invoice ? (string) $invoice->balance_due : null) }"
          data-confirm="This records money received and updates the invoice balance. Recorded payments can only be reversed, not deleted." data-confirm-title="Record this payment?" data-confirm-button="Record payment" data-confirm-tone="primary">
        @csrf

        <x-card title="Invoice">
            @if ($invoice)
                <input type="hidden" name="invoice_id" value="{{ $invoice->id }}">
                <dl class="grid grid-cols-2 gap-4 text-sm sm:grid-cols-4">
                    <div><dt class="text-slate-500">Invoice</dt><dd class="font-medium"><a href="{{ route('invoices.show', $invoice) }}" class="text-indigo-600 hover:underline">{{ $invoice->number }}</a></dd></div>
                    <div><dt class="text-slate-500">Client</dt><dd class="font-medium">{{ $invoice->client->displayName() }}</dd></div>
                    <div><dt class="text-slate-500">Total</dt><dd class="font-medium">{{ money($invoice->grand_total, $invoice->currency) }}</dd></div>
                    <div><dt class="text-slate-500">Outstanding</dt><dd class="font-semibold text-indigo-700">{{ money($invoice->balance_due, $invoice->currency) }}</dd></div>
                </dl>
            @else
                <x-input-label for="invoice_id" value="Invoice *" />
                <select id="invoice_id" name="invoice_id" required class="{{ $input }}"
                        @change="const o = $event.target.selectedOptions[0]; balance = o?.dataset.balance || null; amount = balance || ''">
                    <option value="">Select an unpaid invoice…</option>
                    @foreach ($receivables as $r)
                        <option value="{{ $r->id }}" data-balance="{{ $r->balance_due }}" @selected(old('invoice_id') == $r->id)>
                            {{ $r->number }} · {{ $r->client?->displayName() }} · {{ money($r->balance_due, $r->currency) }} due {{ $r->due_date->format('M j') }}
                        </option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('invoice_id')" class="mt-1" />
                @if ($receivables->isEmpty())
                    <p class="mt-2 text-sm text-slate-500">There are no unpaid issued invoices you can record payments for.</p>
                @endif
            @endif
        </x-card>

        <x-card title="Payment">
            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                @include('payments._fields', ['methods' => $methods])
            </div>
            <p class="mt-4 text-sm text-slate-500" x-show="balance" x-cloak>
                Partial payments are fine. The amount cannot exceed the outstanding balance of <span class="font-medium text-slate-700" x-text="balance"></span>.
            </p>
        </x-card>

        <div class="flex items-center gap-4">
            <x-primary-button><x-icon name="payments" class="h-4 w-4" /> Record payment</x-primary-button>
            <a href="{{ $invoice ? route('invoices.show', $invoice) : route('payments.index') }}" class="text-sm text-slate-600 hover:underline">Cancel</a>
        </div>
    </form>
</x-app-layout>
