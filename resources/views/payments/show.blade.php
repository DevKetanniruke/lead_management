@php
    $input = 'mt-1 block w-full border-slate-300 rounded-lg shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500';
    $invoice = $payment->allocations->first()?->invoice;
@endphp

<x-app-layout :title="'Payment '.$payment->receiptNumber()">
    <x-slot name="header">
        <div class="flex items-center gap-3">
            <h2 class="truncate text-lg font-semibold text-slate-900">Payment {{ $payment->receiptNumber() }}</h2>
            <x-badge :color="$payment->isReversed() ? 'red' : 'green'">{{ ucfirst($payment->status) }}</x-badge>
        </div>
    </x-slot>

    <div class="mx-auto max-w-5xl space-y-6" x-data="{ panel: @js($errors->has('reason') || $errors->has('amount') ? (in_array(old('panel'), ['reverse', 'correct'], true) ? old('panel') : 'reverse') : null) }">
        <x-flash :error-keys="[]" />

        @if ($payment->isReversed())
            <div class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800">
                Reversed on {{ $payment->reversed_at->format('M j, Y H:i') }} by {{ $payment->reverser?->name ?? 'unknown' }}: {{ $payment->reversal_reason }}
                @if ($payment->correctedBy)
                    · Replaced by <a href="{{ route('payments.show', $payment->correctedBy) }}" class="font-medium underline">{{ $payment->correctedBy->receiptNumber() }}</a>
                @endif
            </div>
        @endif

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
            <x-card title="Details" class="lg:col-span-2">
                <dl class="grid grid-cols-1 gap-x-6 gap-y-4 text-sm sm:grid-cols-2">
                    @foreach ([
                        'Amount' => money($payment->amount, $payment->currency),
                        'Payment date' => $payment->payment_date->format('M j, Y'),
                        'Method' => $payment->method,
                        'Reference' => $payment->reference,
                        'Client' => $payment->client->displayName(),
                        'Recorded by' => ($payment->recorder?->name ?? '—').' · '.$payment->created_at->format('M j, Y H:i'),
                    ] as $label => $value)
                        <div><dt class="text-slate-500">{{ $label }}</dt><dd class="font-medium text-slate-900">{{ $value ?: '—' }}</dd></div>
                    @endforeach
                    @if ($payment->corrects)
                        <div><dt class="text-slate-500">Corrects</dt><dd><a href="{{ route('payments.show', $payment->corrects) }}" class="font-medium text-indigo-600 hover:underline">{{ $payment->corrects->receiptNumber() }}</a></dd></div>
                    @endif
                </dl>
                @if ($payment->notes)
                    <p class="mt-4 whitespace-pre-line border-t border-slate-100 pt-4 text-sm text-slate-700">{{ $payment->notes }}</p>
                @endif

                <h4 class="mt-6 text-sm font-semibold text-slate-900">Allocation</h4>
                <ul class="mt-2 divide-y divide-slate-100 rounded-lg border border-slate-200 text-sm">
                    @foreach ($payment->allocations as $a)
                        <li class="flex items-center justify-between px-4 py-2.5">
                            <a href="{{ route('invoices.show', $a->invoice_id) }}" class="font-medium text-indigo-600 hover:underline">{{ $a->invoice?->number }}</a>
                            <span>{{ money($a->amount, $payment->currency) }}</span>
                        </li>
                    @endforeach
                </ul>
            </x-card>

            <div class="space-y-6">
                @if ($invoice)
                    <x-card title="Invoice balance">
                        <dl class="space-y-2 text-sm">
                            <div class="flex justify-between"><dt class="text-slate-500">Total</dt><dd>{{ money($invoice->grand_total, $invoice->currency) }}</dd></div>
                            <div class="flex justify-between"><dt class="text-slate-500">Paid</dt><dd>{{ money($invoice->amount_paid, $invoice->currency) }}</dd></div>
                            <div class="flex justify-between font-semibold"><dt>Outstanding</dt><dd>{{ money($invoice->balance_due, $invoice->currency) }}</dd></div>
                            <div class="pt-1"><x-invoice-status :invoice="$invoice" /></div>
                        </dl>
                    </x-card>
                @endif

                @canany(['reverse', 'correct'], $payment)
                    <x-card title="Fix a mistake">
                        <p class="text-sm text-slate-600">Payments are never deleted. Reverse a payment that didn't happen, or correct one recorded with the wrong details.</p>
                        <div class="mt-3 flex flex-wrap gap-2">
                            @can('reverse', $payment)
                                <button type="button" @click="panel = panel === 'reverse' ? null : 'reverse'" class="rounded-lg border border-red-200 px-3 py-1.5 text-sm font-medium text-red-700 hover:bg-red-50"><x-icon name="undo" class="me-1 inline h-4 w-4" />Reverse</button>
                            @endcan
                            @can('correct', $payment)
                                <button type="button" @click="panel = panel === 'correct' ? null : 'correct'" class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50"><x-icon name="pencil" class="me-1 inline h-4 w-4" />Correct</button>
                            @endcan
                        </div>

                        @can('reverse', $payment)
                            <form method="POST" action="{{ route('payments.reverse', $payment) }}" x-show="panel === 'reverse'" x-cloak class="mt-4 space-y-3"
                                  data-confirm="The payment stays on record as reversed and the invoice balance goes back up by {{ money($payment->amount, $payment->currency) }}." data-confirm-title="Reverse {{ $payment->receiptNumber() }}?" data-confirm-button="Reverse payment">
                                @csrf
                                <input type="hidden" name="panel" value="reverse">
                                <div>
                                    <x-input-label for="reverse_reason" value="Reason *" />
                                    <textarea id="reverse_reason" name="reason" rows="2" required minlength="5" maxlength="1000" class="{{ $input }}" placeholder="e.g. Cheque bounced">{{ old('panel') === 'reverse' ? old('reason') : '' }}</textarea>
                                    @if (old('panel') === 'reverse')<x-input-error :messages="$errors->get('reason')" class="mt-1" />@endif
                                </div>
                                <x-danger-button>Reverse payment</x-danger-button>
                            </form>
                        @endcan

                        @can('correct', $payment)
                            <form method="POST" action="{{ route('payments.correct', $payment) }}" x-show="panel === 'correct'" x-cloak class="mt-4 grid grid-cols-1 gap-3"
                                  x-data="{ amount: @js(old('amount', (string) $payment->amount)) }"
                                  data-confirm="The original payment is reversed and a corrected payment is recorded in its place. Both stay on record." data-confirm-title="Correct this payment?" data-confirm-button="Save correction" data-confirm-tone="primary">
                                @csrf
                                <input type="hidden" name="panel" value="correct">
                                @include('payments._fields', ['methods' => $methods, 'defaults' => [
                                    'amount' => (string) $payment->amount, 'payment_date' => $payment->payment_date->toDateString(),
                                    'method' => $payment->method, 'reference' => $payment->reference, 'notes' => $payment->notes,
                                ]])
                                <div>
                                    <x-input-label for="correct_reason" value="Reason for correction *" />
                                    <textarea id="correct_reason" name="reason" rows="2" required minlength="5" maxlength="1000" class="{{ $input }}">{{ old('panel') === 'correct' ? old('reason') : '' }}</textarea>
                                    @if (old('panel') === 'correct')<x-input-error :messages="$errors->get('reason')" class="mt-1" />@endif
                                </div>
                                <x-primary-button>Save correction</x-primary-button>
                            </form>
                        @endcan
                    </x-card>
                @endcanany

                <x-card title="Audit trail" :padding="false">
                    <ol class="divide-y divide-slate-100">
                        @foreach ($history as $entry)
                            <li class="px-5 py-3 text-sm">
                                <p class="text-slate-800">{{ $entry->description }}</p>
                                <p class="text-xs text-slate-500">{{ $entry->user?->name ?? 'System' }} · {{ $entry->created_at->format('M j, Y H:i') }}</p>
                            </li>
                        @endforeach
                    </ol>
                </x-card>
            </div>
        </div>
    </div>
</x-app-layout>
