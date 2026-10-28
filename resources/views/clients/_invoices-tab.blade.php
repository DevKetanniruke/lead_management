<x-card title="Invoices" :padding="false">
    <x-slot name="actions">
        @include('clients._header-actions')
    </x-slot>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-xs font-medium uppercase tracking-wider text-slate-500">
                <tr><th class="w-12 px-5 py-3 text-slate-500">S.No</th><th class="px-5 py-3">Invoice</th><th class="px-5 py-3">Date</th><th class="px-5 py-3">Due</th><th class="px-5 py-3 text-right">Total</th><th class="px-5 py-3 text-right">Paid</th><th class="px-5 py-3 text-right">Balance</th><th class="px-5 py-3">Status</th></tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($client->invoices as $invoice)
                    <tr>
                        <td class="whitespace-nowrap px-5 py-3 text-xs font-medium text-slate-500">{{ $loop->iteration }}</td>
                        <td class="whitespace-nowrap px-5 py-3"><a href="{{ route('invoices.show', $invoice) }}" class="font-medium text-indigo-600 hover:underline">{{ $invoice->number }}</a></td>
                        <td class="whitespace-nowrap px-5 py-3 text-slate-600">{{ $invoice->invoice_date->format('M j, Y') }}</td>
                        <td class="whitespace-nowrap px-5 py-3 {{ $invoice->isOverdue() ? 'font-medium text-red-600' : 'text-slate-600' }}">{{ $invoice->due_date->format('M j, Y') }}</td>
                        <td class="whitespace-nowrap px-5 py-3 text-right">{{ money($invoice->grand_total, $invoice->currency) }}</td>
                        <td class="whitespace-nowrap px-5 py-3 text-right text-slate-600">{{ money($invoice->amount_paid, $invoice->currency) }}</td>
                        <td class="whitespace-nowrap px-5 py-3 text-right font-medium">{{ $invoice->isIssued() ? money($invoice->balance_due, $invoice->currency) : '—' }}</td>
                        <td class="px-5 py-3"><x-invoice-status :invoice="$invoice" /></td>
                    </tr>
                @empty
                    <tr><td colspan="8"><x-empty-state icon="invoice" title="No invoices yet" /></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-card>
