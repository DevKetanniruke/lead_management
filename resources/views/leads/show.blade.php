<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="truncate text-lg font-semibold text-slate-900">{{ $lead->name }}</h2>
            <div class="flex items-center gap-2">
                @can('convert', $lead)
                    <a href="{{ route('conversions.create', $lead) }}"><x-primary-button type="button"><x-icon name="convert" class="h-4 w-4" /> Convert to Client</x-primary-button></a>
                @endcan
                @includeIf('leads._header-actions')
                <a href="{{ route('leads.edit', $lead) }}"><x-secondary-button type="button">Edit</x-secondary-button></a>
                @can('delete', $lead)
                    <form method="POST" action="{{ route('leads.destroy', $lead) }}" data-confirm="The lead is moved to the bin and hidden from lists." data-confirm-title="Delete this lead?" data-confirm-button="Delete">
                        @csrf @method('DELETE')
                        <x-danger-button>Delete</x-danger-button>
                    </form>
                @endcan
            </div>
        </div>
    </x-slot>

    <div class="">
        <div class="max-w-5xl mx-auto space-y-6">
            @if (session('status'))
                <div class="rounded-md bg-green-50 p-4 text-sm text-green-800">{{ session('status') }}</div>
            @endif

            @if ($lead->client)
                <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-emerald-200 bg-emerald-50 px-5 py-4 text-sm">
                    <div class="flex items-center gap-3 text-emerald-900">
                        <x-icon name="trophy" class="h-5 w-5 text-emerald-600" />
                        <span>Converted to client <strong>{{ $lead->client->displayName() }}</strong> ({{ $lead->client->code() }}){{ $lead->converted_at ? ' on '.$lead->converted_at->format('M j, Y') : '' }}.</span>
                    </div>
                    @can('view', $lead->client)
                        <a href="{{ route('clients.show', $lead->client) }}" class="font-medium text-emerald-800 hover:underline">Open client →</a>
                    @endcan
                </div>
            @elseif ($lead->status->is_won && auth()->user()->can('convert', $lead))
                <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-amber-200 bg-amber-50 px-5 py-4 text-sm text-amber-900">
                    <span>This lead is marked won but has no client record yet.</span>
                    <a href="{{ route('conversions.create', $lead) }}" class="font-medium hover:underline">Convert to client →</a>
                </div>
            @endif

            <div class="bg-white border border-slate-200 shadow-sm rounded-xl p-6">
                <dl class="grid grid-cols-1 sm:grid-cols-3 gap-x-6 gap-y-4 text-sm">
                    @foreach ([
                        'Email' => $lead->email,
                        'Phone' => $lead->phone,
                        'Company' => $lead->company,
                        'Source' => $lead->sourceLabel(),
                        'Assigned to' => $lead->assignee?->name,
                        'Added by' => ($lead->creator?->name ?? 'Not recorded').' on '.$lead->created_at->format('M j, Y'),
                        'Deal value' => number_format($lead->value, 2),
                        'Next follow-up' => $lead->next_follow_up_at?->format('M j, Y H:i'),
                        'Converted' => $lead->converted_at?->format('M j, Y'),
                    ] as $label => $value)
                        <div>
                            <dt class="text-gray-500">{{ $label }}</dt>
                            <dd class="font-medium text-gray-900">{{ $value ?: '—' }}</dd>
                        </div>
                    @endforeach
                    <div>
                        <dt class="text-gray-500">Status</dt>
                        <dd><x-lead-status-badge :status="$lead->status" /></dd>
                    </div>
                </dl>

                @if ($lead->notes)
                    <div class="mt-6 border-t pt-4 text-sm">
                        <div class="text-gray-500">Notes</div>
                        <p class="mt-1 whitespace-pre-line text-gray-900">{{ $lead->notes }}</p>
                    </div>
                @endif
            </div>

            <div class="bg-white border border-slate-200 shadow-sm rounded-xl p-6" x-data="{ type: @js(old('type', 'note')) }">
                <h3 class="font-semibold text-gray-800">Log activity</h3>
                <form method="POST" action="{{ route('leads.activities.store', $lead) }}" class="mt-3 space-y-3">
                    @csrf
                    <div class="flex flex-wrap gap-3">
                        <select name="type" x-model="type" class="border-gray-300 rounded-lg shadow-sm text-sm">
                            @foreach (App\Models\Activity::TYPES as $type)
                                <option value="{{ $type }}">{{ $type === 'task' ? 'Follow-up task' : ucfirst($type) }}</option>
                            @endforeach
                        </select>
                        <input type="datetime-local" name="due_at" value="{{ old('due_at') }}" x-show="type === 'task'" x-cloak
                               class="border-gray-300 rounded-lg shadow-sm text-sm">
                    </div>
                    <textarea name="body" rows="2" required placeholder="What happened / what needs doing?"
                              class="block w-full border-gray-300 rounded-lg shadow-sm text-sm">{{ old('body') }}</textarea>
                    <x-input-error :messages="$errors->get('body')" />
                    <x-input-error :messages="$errors->get('due_at')" />
                    <x-primary-button>Add</x-primary-button>
                </form>

                <ul class="mt-6 divide-y divide-gray-100 text-sm">
                    @forelse ($lead->activities as $activity)
                        <li class="flex items-start justify-between gap-4 py-3">
                            <div>
                                <div class="text-xs text-gray-500">
                                    <span class="font-medium uppercase">{{ $activity->type }}</span>
                                    · {{ $activity->user?->name ?? 'Unknown' }} · {{ $activity->created_at->diffForHumans() }}
                                    @if ($activity->type === 'task')
                                        · due {{ $activity->due_at?->format('M j, H:i') }}
                                        @if ($activity->completed_at) · <span class="text-green-600">done</span> @endif
                                    @endif
                                </div>
                                <div class="mt-1 whitespace-pre-line text-gray-900 {{ $activity->completed_at ? 'line-through text-gray-400' : '' }}">{{ $activity->body }}</div>
                            </div>
                            <div class="flex shrink-0 gap-3">
                                @if ($activity->type === 'task')
                                    <form method="POST" action="{{ route('activities.complete', $activity) }}">
                                        @csrf @method('PATCH')
                                        <button class="text-indigo-600 hover:underline">{{ $activity->completed_at ? 'Reopen' : 'Done' }}</button>
                                    </form>
                                @endif
                                <form method="POST" action="{{ route('activities.destroy', $activity) }}" data-confirm="Remove this entry?" data-confirm-button="Remove">
                                    @csrf @method('DELETE')
                                    <button class="text-gray-500 hover:underline">Remove</button>
                                </form>
                            </div>
                        </li>
                    @empty
                        <li class="py-3 text-gray-500">No activity yet.</li>
                    @endforelse
                </ul>
            </div>

            <div class="bg-white border border-slate-200 shadow-sm rounded-xl p-6">
                <h3 class="font-semibold text-gray-800">Status history</h3>
                <ul class="mt-3 space-y-2 text-sm text-gray-700">
                    @forelse ($lead->statusHistory as $h)
                        <li>
                            {{ $h->fromStatus?->name ?? 'Created' }} → <strong>{{ $h->toStatus->name }}</strong>
                            <span class="text-xs text-gray-500">by {{ $h->user?->name ?? 'system' }}, {{ $h->created_at->format('M j, Y H:i') }}</span>
                        </li>
                    @empty
                        <li class="text-gray-500">No changes recorded.</li>
                    @endforelse
                </ul>
            </div>
        </div>
    </div>
</x-app-layout>
