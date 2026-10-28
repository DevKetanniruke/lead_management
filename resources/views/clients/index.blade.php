@php $field = 'border-slate-300 rounded-lg shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500'; @endphp

<x-app-layout title="Clients">
    <x-slot name="header">
        <div class="flex items-center justify-between gap-3">
            <h2 class="truncate text-lg font-semibold text-slate-900">Clients</h2>
            <div class="flex items-center gap-2">
                @can('export', App\Models\Client::class)
                    <a href="{{ route('clients.export', request()->query()) }}" class="hidden sm:block"><x-secondary-button><x-icon name="download" class="h-4 w-4" /> Export CSV</x-secondary-button></a>
                @endcan
                @can('create', App\Models\Client::class)
                    <a href="{{ route('clients.create') }}"><x-primary-button type="button"><x-icon name="plus" class="h-4 w-4" /> New Client</x-primary-button></a>
                @endcan
            </div>
        </div>
    </x-slot>

    <div class="mx-auto max-w-7xl space-y-4">
        <x-flash />

        <form method="GET" data-ajax-filter="clients-region" class="grid grid-cols-2 gap-3 rounded-xl border border-slate-200 bg-white p-4 shadow-sm md:grid-cols-6">
            <input type="search" name="search" value="{{ request('search') }}" placeholder="Search name, company, email, phone, GSTIN…" class="{{ $field }} col-span-2" aria-label="Search clients">
            <select name="status" class="{{ $field }}" aria-label="Status">
                <option value="">Active &amp; inactive</option>
                @foreach (App\Models\Client::STATUSES as $value => $label)
                    <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                @endforeach
                <option value="all" @selected(request('status') === 'all')>All (incl. archived)</option>
            </select>
            <select name="manager" class="{{ $field }}" aria-label="Account manager">
                <option value="">Any manager</option>
                @foreach ($managers as $m)
                    <option value="{{ $m->id }}" @selected(request('manager') == $m->id)>{{ $m->name }}</option>
                @endforeach
            </select>
            <input type="date" name="from" value="{{ request('from') }}" class="{{ $field }}" aria-label="Converted from" title="Converted from">
            <input type="date" name="to" value="{{ request('to') }}" class="{{ $field }}" aria-label="Converted to" title="Converted to">
            <div class="col-span-2 flex items-center gap-3 md:col-span-6">
                <x-primary-button>Filter</x-primary-button>
                <a href="{{ route('clients.index') }}" class="text-sm text-slate-600 hover:underline">Reset</a>
            </div>
        </form>

        <div id="clients-region" data-ajax-table class="space-y-4">
            <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-medium uppercase tracking-wider text-slate-500">
                        <tr>
                            <th class="w-12 px-4 py-3 text-slate-500">S.No</th>
                            <th class="px-4 py-3"><x-sort-link column="id" :sort="$sort" :dir="$dir">ID</x-sort-link></th>
                            <th class="px-4 py-3"><x-sort-link column="name" :sort="$sort" :dir="$dir">Client</x-sort-link></th>
                            <th class="px-4 py-3">Contact</th>
                            <th class="px-4 py-3">Manager</th>
                            <th class="px-4 py-3"><x-sort-link column="converted_at" :sort="$sort" :dir="$dir">Converted</x-sort-link></th>
                            @include('clients._finance-headings')
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3"><span class="sr-only">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 [&>tr:hover]:bg-slate-50">
                        @forelse ($clients as $client)
                            <tr>
                                <td class="whitespace-nowrap px-4 py-3 text-xs font-medium text-slate-500">{{ $clients->firstItem() ? $clients->firstItem() + $loop->index : $loop->iteration }}</td>
                                <td class="whitespace-nowrap px-4 py-3 font-mono text-xs text-slate-500">{{ $client->code() }}</td>
                                <td class="px-4 py-3">
                                    <a href="{{ route('clients.show', $client) }}" class="font-medium text-slate-900 hover:text-indigo-600">{{ $client->displayName() }}</a>
                                    @if ($client->company_name)<div class="text-xs text-slate-500">{{ $client->name }}</div>@endif
                                </td>
                                <td class="px-4 py-3">
                                    <div class="truncate">{{ $client->email ?: '—' }}</div>
                                    <div class="text-xs text-slate-500">{{ $client->phone }}</div>
                                </td>
                                <td class="whitespace-nowrap px-4 py-3">{{ $client->accountManager?->name ?? '—' }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-slate-600">{{ $client->converted_at?->format('M j, Y') ?? 'Manual' }}</td>
                                @include('clients._finance-cells')
                                <td class="px-4 py-3"><x-badge :color="App\Models\Client::STATUS_COLORS[$client->status] ?? 'gray'">{{ App\Models\Client::STATUSES[$client->status] ?? $client->status }}</x-badge></td>
                                <td class="whitespace-nowrap px-4 py-3 text-right">
                                    <a href="{{ route('clients.show', $client) }}" class="text-indigo-600 hover:underline">View</a>
                                    @can('update', $client)
                                        <a href="{{ route('clients.edit', $client) }}" class="ms-3 text-slate-600 hover:underline">Edit</a>
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="13">
                                <x-empty-state icon="clients" title="No clients found">
                                    Convert a qualified lead from its detail page{{ auth()->user()->can('clients.create') ? ', or add a client manually' : '' }}.
                                </x-empty-state>
                            </td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{ $clients->links() }}
        </div>
    </div>
</x-app-layout>
