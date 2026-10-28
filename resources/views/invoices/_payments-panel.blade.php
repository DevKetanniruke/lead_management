@if (! $invoice->isDraft())
    <x-card title="Payment history" :padding="false">
        @can('recordPayment', $invoice)
            <x-slot name="actions">
                <a href="{{ route('payments.create', ['invoice' => $invoice->id]) }}" class="text-sm font-medium text-indigo-600 hover:underline">Record payment</a>
            </x-slot>
        @endcan
        <ul class="divide-y divide-slate-100">
            @forelse ($invoice->allocations()->with('payment.recorder')->get() as $a)
                <li class="flex items-start justify-between gap-3 px-5 py-3 text-sm">
                    <div class="min-w-0">
                        <a href="{{ route('payments.show', $a->payment) }}" class="font-medium {{ $a->payment->isReversed() ? 'text-slate-400 line-through' : 'text-indigo-600' }} hover:underline">{{ $a->payment->receiptNumber() }}</a>
                        <p class="text-xs text-slate-500">{{ $a->payment->payment_date->format('M j, Y') }} · {{ $a->payment->method }}{{ $a->payment->reference ? ' · '.$a->payment->reference : '' }}</p>
                        @if ($a->payment->isReversed())<p class="text-xs text-red-600">Reversed: {{ Str::limit($a->payment->reversal_reason, 60) }}</p>@endif
                    </div>
                    <span class="shrink-0 font-medium {{ $a->payment->isReversed() ? 'text-slate-400 line-through' : '' }}">{{ money($a->amount, $invoice->currency) }}</span>
                </li>
            @empty
                <li class="px-5 py-4 text-sm text-slate-500">No payments recorded yet.</li>
            @endforelse
        </ul>
    </x-card>
@endif
