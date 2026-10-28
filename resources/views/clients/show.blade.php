@php
    $field = 'border-slate-300 rounded-lg shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500';
    $lead = $client->originalLead;
    $tabs = [
        'overview' => 'Overview',
        'contact' => 'Contact',
        'lead' => 'Original lead',
        'deals' => 'Services / Deals',
        'invoices' => 'Invoices',
        'payments' => 'Payments',
        'history' => 'Activity history',
        'notes' => 'Notes & follow-ups',
    ];
    $openTasks = $client->activities->where('type', 'task')->whereNull('completed_at');
@endphp

<x-app-layout :title="$client->displayName()">
    <x-slot name="header">
        <div class="flex items-center justify-between gap-3">
            <div class="flex min-w-0 items-center gap-3">
                <h2 class="truncate text-lg font-semibold text-slate-900">{{ $client->displayName() }}</h2>
                <x-badge :color="App\Models\Client::STATUS_COLORS[$client->status] ?? 'gray'">{{ App\Models\Client::STATUSES[$client->status] ?? $client->status }}</x-badge>
            </div>
            <div class="flex shrink-0 items-center gap-2">
                @includeIf('clients._header-actions')
                @can('update', $client)
                    <a href="{{ route('clients.edit', $client) }}"><x-secondary-button><x-icon name="pencil" class="h-4 w-4" /> <span class="hidden sm:inline">Edit</span></x-secondary-button></a>
                @endcan
            </div>
        </div>
    </x-slot>

    <div class="mx-auto max-w-7xl space-y-6" x-data="{ tab: (location.hash || '#overview').slice(1) }"
         x-init="if (!@js(array_keys($tabs)).includes(tab)) tab = 'overview'"
         @hashchange.window="tab = (location.hash || '#overview').slice(1)">
        <x-flash />

        {{-- Summary strip --}}
        <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
            <x-stat label="Client ID" :value="$client->code()" :sub="$client->converted_at ? 'Converted '.$client->converted_at->format('M j, Y') : 'Added manually'" icon="clients" />
            <x-stat label="Account manager" :value="$client->accountManager?->name ?? '—'" :sub="$client->accountManager?->email" icon="profile" tone="bg-sky-50 text-sky-600" />
            <x-stat label="Deals" :value="money($client->deals->sum('value'))" :sub="$client->deals->count().' '.Str::plural('deal', $client->deals->count())" icon="trophy" tone="bg-emerald-50 text-emerald-600" />
            @includeFirst(['clients._balance-stat', 'clients._open-tasks-stat'], ['openTasks' => $openTasks])
        </div>

        {{-- Tabs --}}
        <div class="border-b border-slate-200">
            <nav class="-mb-px flex gap-1 overflow-x-auto" role="tablist" aria-label="Client sections">
                @foreach ($tabs as $key => $label)
                    <a href="#{{ $key }}" role="tab" :aria-selected="tab === '{{ $key }}'"
                       class="whitespace-nowrap border-b-2 px-3 py-2.5 text-sm font-medium transition"
                       :class="tab === '{{ $key }}' ? 'border-indigo-600 text-indigo-700' : 'border-transparent text-slate-500 hover:border-slate-300 hover:text-slate-700'">{{ $label }}</a>
                @endforeach
            </nav>
        </div>

        {{-- Overview --}}
        <div x-show="tab === 'overview'" role="tabpanel" class="grid grid-cols-1 gap-6 lg:grid-cols-3">
            <x-card title="Summary" class="lg:col-span-2">
                <dl class="grid grid-cols-1 gap-x-6 gap-y-4 text-sm sm:grid-cols-2">
                    @foreach ([
                        'Contact' => $client->name,
                        'Company' => $client->company_name,
                        'Email' => $client->email,
                        'Phone' => $client->phone,
                        'GSTIN' => $client->gstin,
                        'Place of supply' => $client->state ? $client->state.($client->state_code ? " ({$client->state_code})" : '') : $client->state_code,
                        'Converted by' => $client->convertedBy?->name,
                        'Created by' => $client->creator?->name,
                    ] as $label => $value)
                        <div>
                            <dt class="text-slate-500">{{ $label }}</dt>
                            <dd class="font-medium text-slate-900">{{ $value ?: '—' }}</dd>
                        </div>
                    @endforeach
                </dl>
                @if ($client->notes)
                    <div class="mt-6 border-t border-slate-100 pt-4 text-sm">
                        <div class="text-slate-500">Notes</div>
                        <p class="mt-1 whitespace-pre-line text-slate-900">{{ $client->notes }}</p>
                    </div>
                @endif
            </x-card>

            <div class="space-y-6">
                @can('update', $client)
                    <x-card title="Client status">
                        <div class="flex flex-wrap gap-2">
                            @foreach (App\Models\Client::STATUSES as $value => $label)
                                @continue($value === $client->status)
                                <form method="POST" action="{{ route('clients.status', $client) }}"
                                      @if ($value === 'archived') data-confirm="Archived clients are hidden from the default client list. Their history and invoices are kept." data-confirm-title="Archive this client?" data-confirm-button="Archive" @endif>
                                    @csrf @method('PATCH')
                                    <input type="hidden" name="status" value="{{ $value }}">
                                    <x-secondary-button type="submit">
                                        <x-icon :name="$value === 'archived' ? 'archive' : ($value === 'active' ? 'check' : 'ban')" class="h-4 w-4" /> Mark {{ strtolower($label) }}
                                    </x-secondary-button>
                                </form>
                            @endforeach
                        </div>
                    </x-card>
                @endcan

                <x-card title="Open follow-ups" :padding="false">
                    <ul class="divide-y divide-slate-100">
                        @forelse ($openTasks->sortBy('due_at')->take(5) as $task)
                            <li class="px-5 py-3 text-sm">
                                <p class="text-slate-800">{{ $task->body }}</p>
                                <p class="text-xs {{ $task->due_at?->isPast() ? 'font-medium text-red-600' : 'text-slate-500' }}">Due {{ $task->due_at?->format('M j, H:i') }}</p>
                            </li>
                        @empty
                            <li class="px-5 py-6 text-center text-sm text-slate-500">No open follow-ups.</li>
                        @endforelse
                    </ul>
                </x-card>
            </div>
        </div>

        {{-- Contact --}}
        <div x-show="tab === 'contact'" x-cloak role="tabpanel">
            <x-card title="Contact information">
                <dl class="grid grid-cols-1 gap-x-6 gap-y-4 text-sm sm:grid-cols-3">
                    @foreach ([
                        'Contact name' => $client->name,
                        'Company' => $client->company_name,
                        'Email' => $client->email,
                        'Phone' => $client->phone,
                        'Address' => $client->address,
                        'City' => $client->city,
                        'Postal code' => $client->postal_code,
                        'State' => $client->state,
                        'GST state code' => $client->state_code,
                        'Country' => $client->country,
                        'GSTIN' => $client->gstin,
                    ] as $label => $value)
                        <div>
                            <dt class="text-slate-500">{{ $label }}</dt>
                            <dd class="whitespace-pre-line font-medium text-slate-900">{{ $value ?: '—' }}</dd>
                        </div>
                    @endforeach
                </dl>
            </x-card>
        </div>

        {{-- Original lead --}}
        <div x-show="tab === 'lead'" x-cloak role="tabpanel" class="space-y-6">
            @if ($lead)
                <x-card :title="'Lead #'.$lead->id.' · '.$lead->name">
                    <x-slot name="actions">
                        @if ($lead->trashed())
                            <x-badge color="gray">Deleted lead</x-badge>
                        @elsecan('view', $lead)
                            <a href="{{ route('leads.show', $lead) }}" class="text-sm font-medium text-indigo-600 hover:underline">Open lead</a>
                        @endif
                    </x-slot>
                    <dl class="grid grid-cols-1 gap-x-6 gap-y-4 text-sm sm:grid-cols-3">
                        @foreach ([
                            'Email' => $lead->email,
                            'Phone' => $lead->phone,
                            'Company' => $lead->company,
                            'Source' => $lead->sourceLabel(),
                            'Owner' => $lead->assignee?->name,
                            'Lead value' => money($lead->value),
                            'Created' => $lead->created_at->format('M j, Y'),
                            'Converted' => $lead->converted_at?->format('M j, Y'),
                        ] as $label => $value)
                            <div>
                                <dt class="text-slate-500">{{ $label }}</dt>
                                <dd class="font-medium text-slate-900">{{ $value ?: '—' }}</dd>
                            </div>
                        @endforeach
                        <div>
                            <dt class="text-slate-500">Stage</dt>
                            <dd><x-lead-status-badge :status="$lead->status" /></dd>
                        </div>
                    </dl>
                </x-card>
                <x-card title="Pipeline journey">
                    <ol class="space-y-2 text-sm text-slate-700">
                        @forelse ($lead->statusHistory as $h)
                            <li>{{ $h->fromStatus?->name ?? 'Created' }} → <strong>{{ $h->toStatus->name }}</strong>
                                <span class="text-xs text-slate-500">by {{ $h->user?->name ?? 'system' }}, {{ $h->created_at->format('M j, Y H:i') }}</span></li>
                        @empty
                            <li class="text-slate-500">No changes recorded.</li>
                        @endforelse
                    </ol>
                </x-card>
            @else
                <x-card><x-empty-state icon="leads" title="No original lead">This client was added manually.</x-empty-state></x-card>
            @endif

            @php $otherLeads = $client->leads->where('id', '!=', $client->lead_id); @endphp
            @if ($otherLeads->isNotEmpty())
                <x-card title="Other linked leads" :padding="false">
                    <ul class="divide-y divide-slate-100">
                        @foreach ($otherLeads as $l)
                            <li class="flex items-center justify-between gap-4 px-5 py-3 text-sm">
                                <a href="{{ route('leads.show', $l) }}" class="font-medium text-slate-900 hover:text-indigo-600">{{ $l->name }}</a>
                                <x-lead-status-badge :status="$l->status" />
                            </li>
                        @endforeach
                    </ul>
                </x-card>
            @endif
        </div>

        {{-- Deals --}}
        <div x-show="tab === 'deals'" x-cloak role="tabpanel" class="space-y-6">
            <x-card title="Services / Deals" :padding="false">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50 text-left text-xs font-medium uppercase tracking-wider text-slate-500">
                            <tr>
                                <th class="w-12 px-5 py-3 text-slate-500">S.No</th>
                                <th class="px-5 py-3">Service / product</th><th class="px-5 py-3">Closed</th><th class="px-5 py-3">Source lead</th><th class="px-5 py-3 text-right">Value</th><th class="px-5 py-3"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse ($client->deals as $deal)
                                <tr>
                                    <td class="whitespace-nowrap px-5 py-3 text-xs font-medium text-slate-500">{{ $loop->iteration }}</td>
                                    <td class="px-5 py-3"><span class="font-medium text-slate-900">{{ $deal->title }}</span>
                                        @if ($deal->notes)<p class="text-xs text-slate-500">{{ Str::limit($deal->notes, 120) }}</p>@endif</td>
                                    <td class="whitespace-nowrap px-5 py-3 text-slate-600">{{ $deal->closed_on?->format('M j, Y') ?? '—' }}</td>
                                    <td class="px-5 py-3 text-slate-600">{{ $deal->lead ? '#'.$deal->lead->id.' '.$deal->lead->name : '—' }}</td>
                                    <td class="whitespace-nowrap px-5 py-3 text-right font-medium">{{ money($deal->value) }}</td>
                                    <td class="px-5 py-3 text-right">
                                        @can('update', $client)
                                            <form method="POST" action="{{ route('clients.deals.destroy', [$client, $deal]) }}" data-confirm="Remove this deal from the client?" data-confirm-button="Remove">
                                                @csrf @method('DELETE')
                                                <button class="text-slate-400 hover:text-red-600" aria-label="Remove deal"><x-icon name="trash" class="h-4 w-4" /></button>
                                            </form>
                                        @endcan
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="6"><x-empty-state icon="trophy" title="No deals recorded" /></td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-card>

            @can('update', $client)
                <x-card title="Add a deal">
                    <form method="POST" action="{{ route('clients.deals.store', $client) }}" class="grid grid-cols-1 gap-3 sm:grid-cols-4">
                        @csrf
                        <input name="title" required maxlength="255" placeholder="Service or product" class="{{ $field }} sm:col-span-2" aria-label="Service or product" value="{{ old('title') }}">
                        <input name="value" type="number" step="0.01" min="0" placeholder="Value" class="{{ $field }}" aria-label="Value" value="{{ old('value') }}">
                        <input name="closed_on" type="date" class="{{ $field }}" aria-label="Closed on" value="{{ old('closed_on', now()->toDateString()) }}">
                        <textarea name="notes" rows="2" maxlength="5000" placeholder="Notes (optional)" class="{{ $field }} sm:col-span-4" aria-label="Notes">{{ old('notes') }}</textarea>
                        <div class="sm:col-span-4"><x-primary-button><x-icon name="plus" class="h-4 w-4" /> Add deal</x-primary-button></div>
                        <x-input-error :messages="$errors->get('title')" class="sm:col-span-4" />
                    </form>
                </x-card>
            @endcan
        </div>

        {{-- Invoices --}}
        <div x-show="tab === 'invoices'" x-cloak role="tabpanel">
            @includeFirst(['clients._invoices-tab', 'clients._coming-soon'], ['label' => 'Invoices'])
        </div>

        {{-- Payments --}}
        <div x-show="tab === 'payments'" x-cloak role="tabpanel">
            @includeFirst(['clients._payments-tab', 'clients._coming-soon'], ['label' => 'Payments'])
        </div>

        {{-- Activity history --}}
        <div x-show="tab === 'history'" x-cloak role="tabpanel">
            <x-card title="Activity history" :padding="false">
                <ol class="divide-y divide-slate-100">
                    @forelse ($history as $entry)
                        <li class="flex items-start gap-3 px-5 py-3 text-sm">
                            <span class="mt-1.5 h-2 w-2 shrink-0 rounded-full bg-indigo-400" aria-hidden="true"></span>
                            <div class="min-w-0 flex-1">
                                <p class="text-slate-800">{{ $entry->description }}</p>
                                <p class="text-xs text-slate-500">{{ $entry->user?->name ?? 'System' }} · {{ $entry->created_at->format('M j, Y H:i') }} · <code>{{ $entry->action }}</code></p>
                            </div>
                        </li>
                    @empty
                        <li><x-empty-state icon="log" title="No recorded activity yet" /></li>
                    @endforelse
                </ol>
            </x-card>
        </div>

        {{-- Notes & follow-ups --}}
        <div x-show="tab === 'notes'" x-cloak role="tabpanel" class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            <x-card title="Client notes & follow-ups">
                @can('addNote', $client)
                    <form method="POST" action="{{ route('clients.activities.store', $client) }}" class="space-y-3" x-data="{ type: @js(old('type', 'note')) }">
                        @csrf
                        <div class="flex flex-wrap gap-3">
                            <select name="type" x-model="type" class="{{ $field }}" aria-label="Type">
                                @foreach (App\Models\Activity::TYPES as $type)
                                    <option value="{{ $type }}">{{ $type === 'task' ? 'Follow-up task' : ucfirst($type) }}</option>
                                @endforeach
                            </select>
                            <input type="datetime-local" name="due_at" value="{{ old('due_at') }}" x-show="type === 'task'" x-cloak class="{{ $field }}" aria-label="Due">
                        </div>
                        <textarea name="body" rows="2" required maxlength="5000" placeholder="What happened / what needs doing?" class="{{ $field }} block w-full" aria-label="Details">{{ old('body') }}</textarea>
                        <x-input-error :messages="$errors->get('body')" />
                        <x-input-error :messages="$errors->get('due_at')" />
                        <x-primary-button>Add</x-primary-button>
                    </form>
                @endcan

                <ul class="mt-6 divide-y divide-slate-100 text-sm">
                    @forelse ($client->activities as $activity)
                        @include('clients._activity', ['activity' => $activity, 'editable' => true])
                    @empty
                        <li class="py-3 text-slate-500">No client notes yet.</li>
                    @endforelse
                </ul>
            </x-card>

            <x-card title="From linked leads">
                <ul class="divide-y divide-slate-100 text-sm">
                    @forelse ($leadActivities as $activity)
                        @include('clients._activity', ['activity' => $activity, 'editable' => false])
                    @empty
                        <li class="py-3 text-slate-500">No lead notes or follow-ups.</li>
                    @endforelse
                </ul>
            </x-card>
        </div>
    </div>
</x-app-layout>
