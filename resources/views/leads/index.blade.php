@php
    $field = 'border-gray-300 rounded-lg shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500';
    $sortLink = fn (string $col) => request()->fullUrlWithQuery([
        'sort' => $col,
        'dir' => $sort === $col && $dir === 'asc' ? 'desc' : 'asc',
        'page' => null,
    ]);
    $arrow = fn (string $col) => $sort === $col ? ($dir === 'asc' ? '▲' : '▼') : '';
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="truncate text-lg font-semibold text-slate-900">Leads</h2>
            <a href="{{ route('leads.create') }}"><x-primary-button type="button">New Lead</x-primary-button></a>
        </div>
    </x-slot>

    <div class="">
        <div class="max-w-7xl mx-auto space-y-4">
            @if (session('status'))
                <div class="rounded-md bg-green-50 p-4 text-sm text-green-800">{{ session('status') }}</div>
            @endif

            <form method="GET" data-ajax-filter="leads-region" class="bg-white border border-slate-200 shadow-sm rounded-xl p-4 grid grid-cols-2 md:grid-cols-4 lg:grid-cols-7 gap-3">
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Search…" class="{{ $field }} col-span-2">
                <select name="status" class="{{ $field }}">
                    <option value="">All statuses</option>
                    @foreach ($statuses as $s)
                        <option value="{{ $s->id }}" @selected(request('status') == $s->id)>{{ $s->name }}</option>
                    @endforeach
                </select>
                <select name="source" class="{{ $field }}">
                    <option value="">All sources</option>
                    @foreach ($sources as $s)
                        <option value="{{ $s->id }}" @selected(request('source') == $s->id)>{{ $s->name }}</option>
                    @endforeach
                </select>
                @can('assign', App\Models\Lead::class)
                    <select name="assignee" class="{{ $field }}">
                        <option value="">All assignees</option>
                        @foreach ($users as $u)
                            <option value="{{ $u->id }}" @selected(request('assignee') == $u->id)>{{ $u->name }}</option>
                        @endforeach
                    </select>
                @endcan
                <input type="date" name="from" value="{{ request('from') }}" class="{{ $field }}" title="Created from">
                <input type="date" name="to" value="{{ request('to') }}" class="{{ $field }}" title="Created to">
                <div class="flex gap-2 col-span-2 md:col-span-4 lg:col-span-7">
                    <x-primary-button>Filter</x-primary-button>
                    <a href="{{ route('leads.index') }}" class="self-center text-sm text-gray-600 hover:underline">Reset</a>
                </div>
            </form>

            <div id="leads-region" data-ajax-table class="space-y-4">
            @can('assign', App\Models\Lead::class)
                @if ($errors->any())
                    <div class="rounded-md bg-red-50 p-4 text-sm text-red-800">{{ $errors->first() }}</div>
                @endif
                <form id="bulk-form" method="POST" action="{{ route('leads.bulk-assign') }}"
                      class="flex flex-wrap items-center gap-3 rounded-lg bg-indigo-50 p-3 text-sm">
                    @csrf
                    <span class="text-gray-700">With selected:</span>
                    <select name="assigned_to" required class="{{ $field }}">
                        <option value="">Assign to…</option>
                        @foreach ($users->where('is_active', true) as $u)
                            <option value="{{ $u->id }}">{{ $u->name }}</option>
                        @endforeach
                    </select>
                    <x-primary-button>Reassign</x-primary-button>
                </form>
            @endcan

            <div class="bg-white border border-slate-200 shadow-sm rounded-xl overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                        <tr>
                            @can('assign', App\Models\Lead::class)
                                <th class="px-4 py-3">
                                    <input type="checkbox" aria-label="Select all"
                                           onclick="document.querySelectorAll('.lead-check').forEach(c => c.checked = this.checked)">
                                </th>
                            @endcan
                            <th class="w-12 px-4 py-3 text-slate-500">S.No</th>
                            <th class="px-4 py-3"><a href="{{ $sortLink('name') }}" data-ajax>Name {{ $arrow('name') }}</a></th>
                            <th class="px-4 py-3"><a href="{{ $sortLink('company') }}" data-ajax>Company {{ $arrow('company') }}</a></th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3">Source</th>
                            <th class="px-4 py-3">Assignee</th>
                            <th class="px-4 py-3"><a href="{{ $sortLink('created_at') }}" data-ajax>Added by {{ $arrow('created_at') }}</a></th>
                            <th class="px-4 py-3"><a href="{{ $sortLink('value') }}" data-ajax>Value {{ $arrow('value') }}</a></th>
                            <th class="px-4 py-3"><a href="{{ $sortLink('next_follow_up_at') }}" data-ajax>Follow-up {{ $arrow('next_follow_up_at') }}</a></th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 [&>tr:hover]:bg-slate-50">
                        @forelse ($leads as $lead)
                            <tr>
                                @can('assign', App\Models\Lead::class)
                                    <td class="px-4 py-3">
                                        <input type="checkbox" name="ids[]" value="{{ $lead->id }}" form="bulk-form" class="lead-check">
                                    </td>
                                @endcan
                                <td class="whitespace-nowrap px-4 py-3 text-xs font-medium text-slate-500">{{ $leads->firstItem() ? $leads->firstItem() + $loop->index : $loop->iteration }}</td>
                                <td class="px-4 py-3">
                                    <a href="{{ route('leads.show', $lead) }}" class="font-medium text-indigo-600 hover:underline">{{ $lead->name }}</a>
                                    <div class="text-xs text-gray-500">{{ $lead->email }}</div>
                                </td>
                                <td class="px-4 py-3">{{ $lead->company }}</td>
                                <td class="px-4 py-3"><x-lead-status-badge :status="$lead->status" /></td>
                                <td class="px-4 py-3">{{ $lead->sourceLabel() }}</td>
                                <td class="px-4 py-3">{{ $lead->assignee?->name ?? '—' }}</td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    {!! $lead->creator ? e($lead->creator->name) : '<span class="text-gray-400">Not recorded</span>' !!}
                                    <div class="text-xs text-gray-500">{{ $lead->created_at->format('M j, Y') }}</div>
                                </td>
                                <td class="px-4 py-3">{{ number_format($lead->value, 2) }}</td>
                                <td class="px-4 py-3 {{ $lead->next_follow_up_at?->isPast() && ! $lead->status->is_won && ! $lead->status->is_lost ? 'text-red-600 font-medium' : '' }}">
                                    {{ $lead->next_follow_up_at?->format('M j, H:i') ?? '—' }}
                                </td>
                                 <td class="px-4 py-3 text-right whitespace-nowrap">
                                    <div class="inline-flex items-center justify-end gap-2">
                                    @if ($lead->isConverted())
                                        @can('view', $lead->client)
                                            <a href="{{ route('clients.show', $lead->client) }}"
                                               class="inline-flex items-center gap-1 rounded-lg bg-emerald-50 px-2.5 py-1.5 text-xs font-semibold text-emerald-700 hover:bg-emerald-100">
                                                <x-icon name="clients" class="h-4 w-4" /> Client
                                            </a>
                                        @endcan
                                    @else
                                        @can('convert', $lead)
                                            <a href="{{ route('conversions.create', $lead) }}"
                                               class="inline-flex items-center gap-1 rounded-lg bg-indigo-50 px-2.5 py-1.5 text-xs font-semibold text-indigo-700 hover:bg-indigo-100">
                                                <x-icon name="convert" class="h-4 w-4" /> Convert
                                            </a>
                                        @endcan
                                    @endif
                                    <div class="relative inline-block text-left" x-data="{ open: false }" @click.outside="open = false">
                                        <button @click="open = !open"
                                            class="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 shadow-sm hover:bg-slate-50">
                                            Actions
                                            <svg class="h-3.5 w-3.5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5"/></svg>
                                        </button>

                                        <div x-show="open" x-cloak x-transition
                                             class="absolute right-0 z-30 mt-1 w-44 origin-top-right rounded-xl border border-slate-200 bg-white py-1 shadow-lg">

                                            {{-- View lead --}}
                                            <a href="{{ route('leads.show', $lead) }}"
                                               class="flex items-center gap-2 px-4 py-2 text-sm text-slate-700 hover:bg-slate-50">
                                                <x-icon name="eye" class="h-4 w-4 text-slate-400" /> View
                                            </a>

                                            {{-- Edit lead --}}
                                            @can('update', $lead)
                                            <a href="{{ route('leads.edit', $lead) }}"
                                               class="flex items-center gap-2 px-4 py-2 text-sm text-slate-700 hover:bg-slate-50">
                                                <x-icon name="pencil" class="h-4 w-4 text-slate-400" /> Edit
                                            </a>
                                            @endcan

                                            <div class="my-1 border-t border-slate-100"></div>

                                            @if ($lead->isConverted())
                                                {{-- Already converted → go to client --}}
                                                <a href="{{ route('clients.show', $lead->client_id) }}"
                                                   class="flex items-center gap-2 px-4 py-2 text-sm font-medium text-emerald-700 hover:bg-emerald-50">
                                                    <x-icon name="clients" class="h-4 w-4" /> View Client
                                                </a>
                                            @else
                                                {{-- Convert to client --}}
                                                @can('convert', $lead)
                                                <a href="{{ route('conversions.create', $lead) }}"
                                                   class="flex items-center gap-2 px-4 py-2 text-sm font-medium text-indigo-700 hover:bg-indigo-50">
                                                    <x-icon name="check-circle" class="h-4 w-4" /> Convert to Client
                                                </a>
                                                @endcan

                                                {{-- Add to existing client --}}
                                                @can('create', App\Models\Client::class)
                                                <a href="{{ route('clients.create', ['lead_id' => $lead->id]) }}"
                                                   class="flex items-center gap-2 px-4 py-2 text-sm text-slate-700 hover:bg-slate-50">
                                                    <x-icon name="plus" class="h-4 w-4 text-slate-400" /> Add as Client
                                                </a>
                                                @endcan
                                            @endif

                                            {{-- Delete --}}
                                            @can('delete', $lead)
                                            <div class="my-1 border-t border-slate-100"></div>
                                            <form method="POST" action="{{ route('leads.destroy', $lead) }}"
                                                  onsubmit="return confirm('Delete this lead? This cannot be undone.')">
                                                @csrf @method('DELETE')
                                                <button type="submit"
                                                    class="flex w-full items-center gap-2 px-4 py-2 text-sm text-red-600 hover:bg-red-50">
                                                    <x-icon name="trash" class="h-4 w-4" /> Delete
                                                </button>
                                            </form>
                                            @endcan
                                        </div>
                                    </div>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="11"class="px-4 py-8 text-center text-gray-500">No leads found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{ $leads->links() }}
            </div>
        </div>
    </div>
</x-app-layout>
