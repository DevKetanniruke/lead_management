<x-card title="Payments" :padding="false">
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-xs font-medium uppercase tracking-wider text-slate-500">
                <tr><th class="w-12 px-5 py-3 text-slate-500">S.No</th><th class="px-5 py-3">Receipt</th><th class="px-5 py-3">Date</th><th class="px-5 py-3">Invoice</th><th class="px-5 py-3">Method</th><th class="px-5 py-3 text-right">Amount</th><th class="px-5 py-3">Status</th></tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($client->payments as $p)
                    <tr @class(['text-slate-400' => $p->isReversed()])>
                        <td class="whitespace-nowrap px-5 py-3 text-xs font-medium text-slate-500">{{ $loop->iteration }}</td>
                        <td class="whitespace-nowrap px-5 py-3"><a href="{{ route('payments.show', $p) }}" class="font-medium text-indigo-600 hover:underline">{{ $p->receiptNumber() }}</a></td>
                        <td class="whitespace-nowrap px-5 py-3">{{ $p->payment_date->format('M j, Y') }}</td>
                        <td class="whitespace-nowrap px-5 py-3">{{ $p->allocations->map(fn ($a) => $a->invoice?->number)->implode(', ') }}</td>
                        <td class="whitespace-nowrap px-5 py-3">{{ $p->method }}</td>
                        <td class="whitespace-nowrap px-5 py-3 text-right font-medium {{ $p->isReversed() ? 'line-through' : '' }}">{{ money($p->amount, $p->currency) }}</td>
                        <td class="px-5 py-3"><x-badge :color="$p->isReversed() ? 'red' : 'green'">{{ ucfirst($p->status) }}</x-badge></td>
                    </tr>
                @empty
                    <tr><td colspan="7"><x-empty-state icon="payments" title="No payments yet" /></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-card>
