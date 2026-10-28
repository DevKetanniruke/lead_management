@php $field = 'border-slate-300 rounded-lg shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500'; @endphp

<x-app-layout title="Payments">
    <x-slot name="header">
        <div class="flex items-center justify-between gap-3">
            <h2 class="truncate text-lg font-semibold text-slate-900">Payments</h2>
            <div class="flex items-center gap-2">
                @if (auth()->user()->canAny(['invoices.view_all', 'reports.view']))
                    <a href="{{ route('payments.export', request()->query()) }}" class="hidden sm:block"><x-secondary-button><x-icon name="download" class="h-4 w-4" /> Export CSV</x-secondary-button></a>
                @endif
                @can('create', App\Models\Payment::class)
                    <a href="{{ route('payments.create') }}"><x-primary-button type="button"><x-icon name="plus" class="h-4 w-4" /> Record payment</x-primary-button></a>
                @endcan
            </div>
        </div>
    </x-slot>

    <div class="mx-auto max-w-7xl space-y-4">
        <x-flash />

        <form method="GET" data-ajax-filter="payments-region" class="grid grid-cols-2 gap-3 rounded-xl border border-slate-200 bg-white p-4 shadow-sm md:grid-cols-6">
            <input type="search" name="search" value="{{ request('search') }}" placeholder="Client, invoice no. or reference…" class="{{ $field }} col-span-2" aria-label="Search payments">
            <select name="method" class="{{ $field }}" aria-label="Method">
                <option value="">All methods</option>
                @foreach ($methods as $m)<option value="{{ $m }}" @selected(request('method') === $m)>{{ $m }}</option>@endforeach
            </select>
            <select name="status" class="{{ $field }}" aria-label="Status">
                <option value="">Any status</option>
                <option value="completed" @selected(request('status') === 'completed')>Completed</option>
                <option value="reversed" @selected(request('status') === 'reversed')>Reversed</option>
            </select>
            <input type="date" name="from" value="{{ request('from') }}" class="{{ $field }}" aria-label="From" title="Payment date from">
            <input type="date" name="to" value="{{ request('to') }}" class="{{ $field }}" aria-label="To" title="Payment date to">
            <div class="col-span-2 flex items-center gap-3 md:col-span-6">
                <x-primary-button>Filter</x-primary-button>
                <a href="{{ route('payments.index') }}" class="text-sm text-slate-600 hover:underline">Reset</a>
            </div>
        </form>

        <div id="payments-region" data-ajax-table class="space-y-4">
            <p class="text-sm text-slate-600">Received (completed, matching filters): <strong class="text-slate-900">{{ money($received, $currency) }}</strong></p>

            <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-medium uppercase tracking-wider text-slate-500">
                        <tr>
                            <th class="w-12 px-4 py-3 text-slate-500">S.No</th>
                            <th class="px-4 py-3">Receipt</th>
                            <th class="px-4 py-3">Date</th>
                            <th class="px-4 py-3">Client</th>
                            <th class="px-4 py-3">Invoice</th>
                            <th class="px-4 py-3">Method</th>
                            <th class="px-4 py-3">Reference</th>
                            <th class="px-4 py-3 text-right">Amount</th>
                            <th class="px-4 py-3">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 [&>tr:hover]:bg-slate-50">
                        @forelse ($payments as $p)
                            <tr @class(['text-slate-400' => $p->isReversed()])>
                                <td class="whitespace-nowrap px-4 py-3 text-xs font-medium text-slate-500">{{ $payments->firstItem() ? $payments->firstItem() + $loop->index : $loop->iteration }}</td>
                                <td class="whitespace-nowrap px-4 py-3"><a href="{{ route('payments.show', $p) }}" class="font-medium text-indigo-600 hover:underline">{{ $p->receiptNumber() }}</a></td>
                                <td class="whitespace-nowrap px-4 py-3">{{ $p->payment_date->format('M j, Y') }}</td>
                                <td class="px-4 py-3">{{ $p->client?->displayName() }}</td>
                                <td class="whitespace-nowrap px-4 py-3">
                                    @foreach ($p->allocations as $a)
                                        <a href="{{ route('invoices.show', $a->invoice_id) }}" class="hover:underline">{{ $a->invoice?->number }}</a>
                                    @endforeach
                                </td>
                                <td class="whitespace-nowrap px-4 py-3">{{ $p->method }}</td>
                                <td class="px-4 py-3">{{ $p->reference ?: '—' }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-right font-medium {{ $p->isReversed() ? 'line-through' : '' }}">{{ money($p->amount, $p->currency) }}</td>
                                <td class="px-4 py-3"><x-badge :color="$p->isReversed() ? 'red' : 'green'">{{ ucfirst($p->status) }}</x-badge></td>
                            </tr>
                        @empty
                            <tr><td colspan="9">
                                <x-empty-state icon="payments" title="No payments found">Payments are recorded against issued invoices from the invoice page.</x-empty-state>
                            </td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{ $payments->links() }}
        </div>
    </div>
</x-app-layout>
