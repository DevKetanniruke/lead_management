@php
    $input    = 'mt-1 block w-full border-gray-300 rounded-lg shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm';
    $label    = 'block text-sm font-medium text-slate-700 mb-1';
    $canAssign = auth()->user()->can('assign', App\Models\Lead::class);
    $isAdmin   = auth()->user()->hasAnyRole(['admin', 'manager']);
@endphp

<form method="POST" action="{{ $action }}" class="space-y-8">
    @csrf
    @isset($method) @method($method) @endisset

    {{-- ── Section: Contact Info ───────────────────────────────────── --}}
    <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
        <h3 class="mb-4 text-sm font-semibold uppercase tracking-wide text-slate-500">Contact Information</h3>
        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">

            <div>
                <label for="name" class="{{ $label }}">Full Name <span class="text-red-500">*</span></label>
                <input id="name" name="name" type="text" required
                       class="{{ $input }}" value="{{ old('name', $lead->name) }}"
                       placeholder="e.g. Rahul Sharma">
                <x-input-error :messages="$errors->get('name')" class="mt-1" />
            </div>

            <div>
                <label for="company" class="{{ $label }}">Company / Organisation</label>
                <input id="company" name="company" type="text"
                       class="{{ $input }}" value="{{ old('company', $lead->company) }}"
                       placeholder="e.g. Acme Pvt Ltd">
                <x-input-error :messages="$errors->get('company')" class="mt-1" />
            </div>

            <div>
                <label for="email" class="{{ $label }}">Email Address</label>
                <input id="email" name="email" type="email"
                       class="{{ $input }}" value="{{ old('email', $lead->email) }}"
                       placeholder="email@example.com">
                <x-input-error :messages="$errors->get('email')" class="mt-1" />
            </div>

            <div>
                <label for="phone" class="{{ $label }}">Phone Number</label>
                <input id="phone" name="phone" type="text"
                       class="{{ $input }}" value="{{ old('phone', $lead->phone) }}"
                       placeholder="+91 9XXXXXXXXX">
                <x-input-error :messages="$errors->get('phone')" class="mt-1" />
            </div>

        </div>
    </div>

    {{-- ── Section: Lead Details ────────────────────────────────────── --}}
    <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
        <h3 class="mb-4 text-sm font-semibold uppercase tracking-wide text-slate-500">Lead Details</h3>
        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">

            {{-- Status --}}
            <div>
                <label for="lead_status_id" class="{{ $label }}">Status <span class="text-red-500">*</span></label>
                <select id="lead_status_id" name="lead_status_id" required class="{{ $input }}">
                    <option value="">— Select Status —</option>
                    @foreach ($statuses as $status)
                        <option value="{{ $status->id }}"
                            @selected(old('lead_status_id', $lead->lead_status_id) == $status->id)
                            @if($status->is_won) data-won="1" @endif
                            @if($status->is_lost) class="text-red-600" @endif>
                            {{ $status->name }}
                            @if($status->is_won) ✓ @elseif($status->is_lost) ✗ @endif
                        </option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('lead_status_id')" class="mt-1" />
            </div>

            {{-- Source --}}
            <div x-data="{
                    source: '{{ old('lead_source_id', $lead->lead_source_id) }}',
                    otherId: '{{ $sources->firstWhere('name', App\Models\LeadSource::OTHER)?->id }}'
                 }">
                <label for="lead_source_id" class="{{ $label }}">Lead Source</label>
                <select id="lead_source_id" name="lead_source_id" class="{{ $input }}"
                        x-model="source">
                    <option value="">— Select Source —</option>
                    @foreach ($sources as $src)
                        <option value="{{ $src->id }}">{{ $src->name }}</option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('lead_source_id')" class="mt-1" />

                <div x-show="otherId && source == otherId" x-cloak class="mt-3">
                    <label for="lead_source_other" class="{{ $label }}">Specify source <span class="text-red-500">*</span></label>
                    <input id="lead_source_other" name="lead_source_other" type="text" maxlength="255"
                           placeholder="e.g. Trade show, Partner, Newspaper ad"
                           x-bind:required="otherId && source == otherId"
                           class="{{ $input }}"
                           value="{{ old('lead_source_other', $lead->lead_source_other) }}">
                    <x-input-error :messages="$errors->get('lead_source_other')" class="mt-1" />
                </div>
            </div>

            {{-- Deal Value --}}
            <div>
                <label for="value" class="{{ $label }}">Deal Value (₹)</label>
                <input id="value" name="value" type="number" step="0.01" min="0"
                       class="{{ $input }}" value="{{ old('value', $lead->value) }}"
                       placeholder="0.00">
                <x-input-error :messages="$errors->get('value')" class="mt-1" />
            </div>

            {{-- Next Follow-up --}}
            <div>
                <label for="next_follow_up_at" class="{{ $label }}">Next Follow-up Date & Time</label>
                <input id="next_follow_up_at" name="next_follow_up_at" type="datetime-local"
                       class="{{ $input }}"
                       value="{{ old('next_follow_up_at', $lead->next_follow_up_at?->format('Y-m-d\TH:i')) }}">
                <x-input-error :messages="$errors->get('next_follow_up_at')" class="mt-1" />
            </div>

        </div>
    </div>

    {{-- ── Section: Assignment & Management ───────────────────────── --}}
    <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
        <h3 class="mb-4 text-sm font-semibold uppercase tracking-wide text-slate-500">Assignment & Management</h3>
        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">

            {{-- Assigned Sales User --}}
            @if ($canAssign)
            <div>
                <label for="assigned_to" class="{{ $label }}">Assigned To (Sales User)</label>
                <select id="assigned_to" name="assigned_to" class="{{ $input }}">
                    <option value="">{{ $lead->exists ? '— Unassigned —' : '— Auto-assign (round-robin) —' }}</option>
                    @foreach ($users as $user)
                        <option value="{{ $user->id }}"
                            @selected(old('assigned_to', $lead->assigned_to) == $user->id)>
                            {{ $user->name }}
                        </option>
                    @endforeach
                </select>
                <p class="mt-1 text-xs text-slate-400">Only active users are listed. Leaving blank auto-assigns via round-robin.</p>
                <x-input-error :messages="$errors->get('assigned_to')" class="mt-1" />
            </div>
            @endif

            {{-- Link to Existing Client --}}
            @if (isset($clients) && $clients->isNotEmpty())
            <div>
                <label for="client_id" class="{{ $label }}">Link to Client (optional)</label>
                <select id="client_id" name="client_id" class="{{ $input }}">
                    <option value="">— No client linked —</option>
                    @foreach ($clients as $client)
                        <option value="{{ $client->id }}"
                            @selected(old('client_id', $lead->client_id ?? '') == $client->id)>
                            {{ $client->name }}{{ $client->company_name ? ' — '.$client->company_name : '' }}
                        </option>
                    @endforeach
                </select>
                <p class="mt-1 text-xs text-slate-400">Link this lead to an existing client record, or use Convert to Client for new conversions.</p>
                <x-input-error :messages="$errors->get('client_id')" class="mt-1" />
            </div>
            @endif

        </div>
    </div>

    {{-- ── Section: Notes ──────────────────────────────────────────── --}}
    <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
        <h3 class="mb-4 text-sm font-semibold uppercase tracking-wide text-slate-500">Notes</h3>
        <label for="notes" class="{{ $label }}">Internal Notes</label>
        <textarea id="notes" name="notes" rows="4"
                  class="{{ $input }}"
                  placeholder="Add any additional context, requirements, or background about this lead…">{{ old('notes', $lead->notes) }}</textarea>
        <x-input-error :messages="$errors->get('notes')" class="mt-1" />
    </div>

    {{-- ── Actions ─────────────────────────────────────────────────── --}}
    <div class="flex items-center gap-4">
        <x-primary-button>
            {{ $lead->exists ? 'Update Lead' : 'Create Lead' }}
        </x-primary-button>
        <a href="{{ route('leads.index') }}"
           class="text-sm font-medium text-slate-600 hover:text-slate-900 hover:underline">
            Cancel
        </a>
    </div>

</form>
