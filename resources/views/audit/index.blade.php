@php $field = 'border-slate-300 rounded-lg shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500'; @endphp

<x-app-layout title="Activity Log">
    <x-slot name="header">
        <h2 class="truncate text-lg font-semibold text-slate-900">Activity Log</h2>
    </x-slot>

    <div class="mx-auto max-w-7xl space-y-4">
        <form method="GET" data-ajax-filter="audit-region" class="grid grid-cols-2 gap-3 rounded-xl border border-slate-200 bg-white p-4 shadow-sm md:grid-cols-6">
            <input type="search" name="search" value="{{ request('search') }}" placeholder="Search descriptions…" class="{{ $field }} col-span-2" aria-label="Search">
            <select name="action" class="{{ $field }}" aria-label="Area">
                <option value="">All areas</option>
                @foreach ($actions as $a)
                    <option value="{{ $a }}." @selected(request('action') === "$a.")>{{ ucfirst($a) }}</option>
                @endforeach
            </select>
            <select name="user" class="{{ $field }}" aria-label="Actor">
                <option value="">Anyone</option>
                @foreach ($users as $u)
                    <option value="{{ $u->id }}" @selected(request('user') == $u->id)>{{ $u->name }}</option>
                @endforeach
            </select>
            <input type="date" name="from" value="{{ request('from') }}" class="{{ $field }}" aria-label="From date">
            <input type="date" name="to" value="{{ request('to') }}" class="{{ $field }}" aria-label="To date">
            <div class="col-span-2 flex items-center gap-3 md:col-span-6">
                <x-primary-button>Filter</x-primary-button>
                <a href="{{ route('audit.index') }}" class="text-sm text-slate-600 hover:underline">Reset</a>
            </div>
        </form>

        <div id="audit-region" data-ajax-table class="space-y-4">
            <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-medium uppercase tracking-wider text-slate-500">
                        <tr>
                            <th class="w-12 px-4 py-3 text-slate-500">S.No</th>
                            <th class="px-4 py-3">When</th>
                            <th class="px-4 py-3">Actor</th>
                            <th class="px-4 py-3">Action</th>
                            <th class="px-4 py-3">Details</th>
                            <th class="px-4 py-3">Record</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 align-top">
                        @forelse ($logs as $log)
                            <tr>
                                <td class="whitespace-nowrap px-4 py-3 text-xs font-medium text-slate-500">{{ $logs->firstItem() ? $logs->firstItem() + $loop->index : $loop->iteration }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-slate-600" title="{{ $log->created_at }}">{{ $log->created_at->format('M j, Y H:i') }}</td>
                                <td class="whitespace-nowrap px-4 py-3">{{ $log->user?->name ?? 'System' }}</td>
                                <td class="whitespace-nowrap px-4 py-3"><code class="rounded bg-slate-100 px-1.5 py-0.5 text-xs text-slate-700">{{ $log->action }}</code></td>
                                <td class="px-4 py-3">
                                    <p class="text-slate-800">{{ $log->description }}</p>
                                    @if ($log->properties)
                                        <details class="mt-1 text-xs text-slate-500">
                                            <summary class="cursor-pointer select-none">Data</summary>
                                            <pre class="mt-1 max-w-xl overflow-x-auto whitespace-pre-wrap rounded bg-slate-50 p-2">{{ json_encode($log->properties, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
                                        </details>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-xs text-slate-500">{{ $log->subject_type ? class_basename($log->subject_type).' #'.$log->subject_id : '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6"><x-empty-state icon="log" title="No activity recorded">Actions such as user changes, conversions, invoices and payments appear here.</x-empty-state></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            {{ $logs->links() }}
        </div>
    </div>
</x-app-layout>
