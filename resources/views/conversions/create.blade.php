@php
    $input = 'mt-1 block w-full border-slate-300 rounded-lg shadow-sm focus:border-indigo-500 focus:ring-indigo-500';
    $defaultManager = $lead->assignee?->is_active ? $lead->assigned_to : auth()->id();
    $mode = old('mode', 'new');
@endphp

<x-app-layout title="Convert lead">
    <x-slot name="header">
        <div class="flex items-center justify-between gap-3">
            <h2 class="truncate text-lg font-semibold text-slate-900">Convert to Client: {{ $lead->name }}</h2>
            <a href="{{ route('leads.show', $lead) }}" class="text-sm text-slate-600 hover:underline">Back to lead</a>
        </div>
    </x-slot>

    <form method="POST" action="{{ route('conversions.store', $lead) }}" class="mx-auto max-w-5xl space-y-6"
          x-data="{ mode: @js($mode), clientId: @js(old('client_id')) }"
          data-confirm="This creates the client record, links it to the lead and marks the lead Won / Converted." data-confirm-title="Convert this lead?" data-confirm-button="Convert to client" data-confirm-tone="primary">
        @csrf

        <x-flash :error-keys="['client_id']" />

        {{-- Lead summary --}}
        <div class="flex flex-wrap items-center gap-x-6 gap-y-2 rounded-xl border border-indigo-100 bg-indigo-50/60 px-5 py-4 text-sm">
            <span class="font-medium text-indigo-900">Lead #{{ $lead->id }}</span>
            <span class="text-slate-700">Stage: <x-lead-status-badge :status="$lead->status" /></span>
            <span class="text-slate-700">Owner: {{ $lead->assignee?->name ?? 'Unassigned' }}</span>
            <span class="text-slate-700">Value: {{ money($lead->value) }}</span>
            <span class="text-slate-500">Notes and follow-ups stay on the lead and are shown on the client.</span>
        </div>

        {{-- Duplicate check --}}
        @if ($matches->isNotEmpty())
            <x-card title="Possible existing clients">
                <p class="text-sm text-slate-600">These clients share this lead's email, phone or company. To avoid a duplicate, link the lead to one of them instead.</p>
                <div class="mt-4 space-y-2">
                    @foreach ($matches as $match)
                        <label class="flex cursor-pointer items-start gap-3 rounded-lg border p-3 text-sm transition"
                               :class="mode === 'link' && clientId == '{{ $match->id }}' ? 'border-indigo-400 bg-indigo-50' : 'border-slate-200 hover:bg-slate-50'">
                            <input type="radio" name="client_id" value="{{ $match->id }}" x-model="clientId" @change="mode = 'link'"
                                   class="mt-0.5 border-slate-300 text-indigo-600" :disabled="mode !== 'link'">
                            <span class="min-w-0">
                                <span class="font-medium text-slate-900">{{ $match->displayName() }}</span>
                                <span class="text-slate-500">· {{ $match->code() }} · {{ \App\Models\Client::STATUSES[$match->status] }}</span>
                                <span class="block truncate text-slate-500">{{ collect([$match->name, $match->email, $match->phone, $match->gstin])->filter()->implode(' · ') }}{{ $match->accountManager ? ' · Manager: '.$match->accountManager->name : '' }}</span>
                            </span>
                        </label>
                    @endforeach
                </div>
                <div class="mt-4 flex flex-wrap gap-2" role="radiogroup" aria-label="Conversion mode">
                    <input type="hidden" name="mode" :value="mode">
                    <button type="button" @click="mode = 'new'; clientId = null" class="rounded-lg px-3 py-1.5 text-sm font-medium"
                            :class="mode === 'new' ? 'bg-indigo-600 text-white' : 'border border-slate-300 bg-white text-slate-700'">Create a new client</button>
                    <button type="button" @click="mode = 'link'" class="rounded-lg px-3 py-1.5 text-sm font-medium"
                            :class="mode === 'link' ? 'bg-indigo-600 text-white' : 'border border-slate-300 bg-white text-slate-700'">Link to the selected client</button>
                </div>
            </x-card>
        @else
            <input type="hidden" name="mode" value="new">
        @endif

        {{-- Client details --}}
        <x-card title="Client information" x-show="mode === 'new'">
            <p class="-mt-1 mb-5 text-sm text-slate-500">Prefilled from the lead. Review and complete the details.</p>
            <fieldset class="grid grid-cols-1 gap-5 sm:grid-cols-2" :disabled="mode !== 'new'">
                <div>
                    <x-input-label for="name" value="Client / contact name *" />
                    <input id="name" name="name" type="text" maxlength="255" class="{{ $input }}" value="{{ old('name', $lead->name) }}" x-bind:required="mode === 'new'">
                    <x-input-error :messages="$errors->get('name')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="company_name" value="Company name" />
                    <input id="company_name" name="company_name" type="text" maxlength="255" class="{{ $input }}" value="{{ old('company_name', $lead->company) }}">
                    <x-input-error :messages="$errors->get('company_name')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="email" value="Email" />
                    <input id="email" name="email" type="email" maxlength="255" class="{{ $input }}" value="{{ old('email', $lead->email) }}">
                    <x-input-error :messages="$errors->get('email')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="phone" value="Phone" />
                    <input id="phone" name="phone" type="tel" maxlength="30" class="{{ $input }}" value="{{ old('phone', $lead->phone) }}">
                    <x-input-error :messages="$errors->get('phone')" class="mt-2" />
                </div>
                @include('clients._address-fields', ['client' => new \App\Models\Client(['country' => 'India'])])
            </fieldset>
        </x-card>

        {{-- Deal --}}
        <x-card title="Deal details">
            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                <div class="sm:col-span-2" x-data="{ service: @js(old('service', '')) }">
                    <x-input-label for="service" value="Service or product purchased *" />
                    <select id="service" name="service" required x-model="service" class="{{ $input }}">
                        <option value="">Choose a service or product</option>
                        @foreach (\App\Support\PurchasedServices::OPTIONS as $option)
                            <option value="{{ $option }}">{{ $option }}</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('service')" class="mt-2" />
                    <div x-show="service === 'Other'" x-cloak class="mt-3">
                        <x-input-label for="custom_service" value="Describe the service or product *" />
                        <input id="custom_service" name="custom_service" type="text" maxlength="255" x-bind:required="service === 'Other'" class="{{ $input }}" value="{{ old('custom_service') }}" placeholder="Enter the service or product">
                        <x-input-error :messages="$errors->get('custom_service')" class="mt-2" />
                    </div>
                </div>
                <div>
                    <x-input-label for="deal_value" value="Deal value" />
                    <input id="deal_value" name="deal_value" type="number" step="0.01" min="0" class="{{ $input }}" value="{{ old('deal_value', $lead->value) }}">
                    <x-input-error :messages="$errors->get('deal_value')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="converted_at" value="Conversion date *" />
                    <input id="converted_at" name="converted_at" type="date" required max="{{ now()->toDateString() }}" class="{{ $input }}" value="{{ old('converted_at', now()->toDateString()) }}">
                    <x-input-error :messages="$errors->get('converted_at')" class="mt-2" />
                </div>
                <div x-show="mode === 'new'">
                    <x-input-label for="account_manager_id" value="Account manager *" />
                    @if ($canAssignManager)
                        <select id="account_manager_id" name="account_manager_id" class="{{ $input }}" x-bind:disabled="mode !== 'new'" x-bind:required="mode === 'new'">
                            @foreach ($managers as $m)
                                <option value="{{ $m->id }}" @selected(old('account_manager_id', $defaultManager) == $m->id)>{{ $m->name }}</option>
                            @endforeach
                        </select>
                    @else
                        <p class="mt-2 text-sm text-slate-700">{{ auth()->user()->name }} <span class="text-slate-500">(you)</span></p>
                    @endif
                    <x-input-error :messages="$errors->get('account_manager_id')" class="mt-2" />
                </div>
                <div class="sm:col-span-2">
                    <x-input-label for="notes" value="Notes" />
                    <textarea id="notes" name="notes" rows="3" maxlength="5000" class="{{ $input }}" placeholder="Anything the account team should know">{{ old('notes') }}</textarea>
                    <x-input-error :messages="$errors->get('notes')" class="mt-2" />
                </div>
            </div>
        </x-card>

        <div class="flex items-center gap-4">
            <x-primary-button><x-icon name="convert" class="h-4 w-4" /> <span x-text="mode === 'link' ? 'Link lead to client' : 'Convert to client'">Convert to client</span></x-primary-button>
            <a href="{{ route('leads.show', $lead) }}" class="text-sm text-slate-600 hover:underline">Cancel</a>
        </div>
    </form>
</x-app-layout>
