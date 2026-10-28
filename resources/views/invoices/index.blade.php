@php $field = 'border-slate-300 rounded-lg shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500'; @endphp

<x-app-layout title="Invoices">
    <x-slot name="header">
        <div class="flex items-center justify-between gap-3">
            <h2 class="truncate text-lg font-semibold text-slate-900">Invoices</h2>
            <div class="flex items-center gap-2">
                @if (auth()->user()->canAny(['invoices.view_all', 'reports.view', 'clients.export']))
                    <a href="{{ route('invoices.export', request()->query()) }}" class="hidden sm:block"><x-secondary-button><x-icon name="download" class="h-4 w-4" /> Export CSV</x-secondary-button></a>
                @endif
                @can('create', App\Models\Invoice::class)
                    <a href="{{ route('invoices.create') }}"><x-primary-button type="button"><x-icon name="plus" class="h-4 w-4" /> New Invoice</x-primary-button></a>
                @endcan
            </div>
        </div>
    </x-slot>

    <div class="mx-auto max-w-7xl space-y-4">
        <x-flash />

        <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
            <x-stat label="Outstanding" :value="money($summary['outstanding'], $currency)" sub="Issued, not yet paid" icon="invoice" :href="route('invoices.index', ['status' => 'unpaid'])" />
            <x-stat label="Overdue" :value="money($summary['overdue'], $currency)" :sub="$summary['overdue_count'].' '.Str::plural('invoice', $summary['overdue_count']).' past due'" icon="alert" :alert="$summary['overdue_count'] > 0" :href="route('invoices.index', ['status' => 'overdue'])" />
            <x-stat label="Drafts" :value="number_format($summary['drafts'])" sub="Not yet issued" icon="pencil" tone="bg-slate-100 text-slate-600" :href="route('invoices.index', ['status' => 'draft'])" />
            <x-stat label="Paid" value="View" sub="Fully settled invoices" icon="check" tone="bg-emerald-50 text-emerald-600" :href="route('invoices.index', ['status' => 'paid'])" />
        </div>

        <form method="GET" data-ajax-filter="invoices-region" class="grid grid-cols-2 gap-3 rounded-xl border border-slate-200 bg-white p-4 shadow-sm md:grid-cols-6">
            <input type="search" name="search" value="{{ request('search') }}" placeholder="Invoice no. or client…" class="{{ $field }} col-span-2" aria-label="Search invoices">
            <select name="status" class="{{ $field }}" aria-label="Status">
                <option value="">All statuses</option>
                @foreach (App\Models\Invoice::DISPLAY_STATUSES as $value => $label)
                    <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                @endforeach
                <option value="unpaid" @selected(request('status') === 'unpaid')>Unpaid (any)</option>
            </select>
            <select name="client" class="{{ $field }}" aria-label="Client">
                <option value="">All clients</option>
                @foreach ($clients as $c)
                    <option value="{{ $c->id }}" @selected(request('client') == $c->id)>{{ $c->company_name ?: $c->name }}</option>
                @endforeach
            </select>
            <input type="date" name="from" value="{{ request('from') }}" class="{{ $field }}" aria-label="Invoice date from" title="Invoice date from">
            <input type="date" name="to" value="{{ request('to') }}" class="{{ $field }}" aria-label="Invoice date to" title="Invoice date to">
            <div class="col-span-2 flex items-center gap-3 md:col-span-6">
                <x-primary-button>Filter</x-primary-button>
                <a href="{{ route('invoices.index') }}" class="text-sm text-slate-600 hover:underline">Reset</a>
            </div>
        </form>

        <div id="invoices-region" data-ajax-table class="space-y-4">
            <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-medium uppercase tracking-wider text-slate-500">
                        <tr>
                            <th class="w-12 px-4 py-3 text-slate-500">S.No</th>
                            <th class="px-4 py-3"><x-sort-link column="number" :sort="$sort" :dir="$dir">Invoice</x-sort-link></th>
                            <th class="px-4 py-3">Client</th>
                            <th class="px-4 py-3"><x-sort-link column="invoice_date" :sort="$sort" :dir="$dir">Date</x-sort-link></th>
                            <th class="px-4 py-3"><x-sort-link column="due_date" :sort="$sort" :dir="$dir">Due</x-sort-link></th>
                            <th class="px-4 py-3 text-right"><x-sort-link column="grand_total" :sort="$sort" :dir="$dir">Total</x-sort-link></th>
                            <th class="px-4 py-3 text-right">Paid</th>
                            <th class="px-4 py-3 text-right"><x-sort-link column="balance_due" :sort="$sort" :dir="$dir">Balance</x-sort-link></th>
                            <th class="px-4 py-3">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 [&>tr:hover]:bg-slate-50">
                        @forelse ($invoices as $invoice)
                            <tr>
                                <td class="whitespace-nowrap px-4 py-3 text-xs font-medium text-slate-500">{{ $invoices->firstItem() ? $invoices->firstItem() + $loop->index : $loop->iteration }}</td>
                                <td class="whitespace-nowrap px-4 py-3"><a href="{{ route('invoices.show', $invoice) }}" class="font-medium text-indigo-600 hover:underline">{{ $invoice->number }}</a></td>
                                <td class="px-4 py-3">{{ $invoice->client?->displayName() }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-slate-600">{{ $invoice->invoice_date->format('M j, Y') }}</td>
                                <td class="whitespace-nowrap px-4 py-3 {{ $invoice->isOverdue() ? 'font-medium text-red-600' : 'text-slate-600' }}">
                                    {{ $invoice->due_date->format('M j, Y') }}
                                    @if ($invoice->isOverdue())<span class="block text-xs">{{ $invoice->daysOverdue() }}d overdue</span>@endif
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-right">{{ money($invoice->grand_total, $invoice->currency) }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-right text-slate-600">{{ money($invoice->amount_paid, $invoice->currency) }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-right font-medium">{{ $invoice->isIssued() ? money($invoice->balance_due, $invoice->currency) : '—' }}</td>
                                <td class="px-4 py-3"><x-invoice-status :invoice="$invoice" /></td>
                            </tr>
                        @empty
                            <tr><td colspan="9">
                                <x-empty-state icon="invoice" title="No invoices found" :action="auth()->user()->can('invoices.create') ? 'New invoice' : null" :href="route('invoices.create')">
                                    Create an invoice from a client profile, a converted lead, or here.
                                </x-empty-state>
                            </td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{ $invoices->links() }}
        </div>
    </div>
</x-app-layout>
