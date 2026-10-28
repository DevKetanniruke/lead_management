@php $input = 'mt-1 block w-full border-slate-300 rounded-lg shadow-sm focus:border-indigo-500 focus:ring-indigo-500'; @endphp

<form method="POST" action="{{ $action }}" class="space-y-6">
    @csrf
    @isset($method) @method($method) @endisset

    <x-card title="Contact information">
        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
            <div>
                <x-input-label for="name" value="Client / contact name *" />
                <input id="name" name="name" type="text" required maxlength="255" class="{{ $input }}" value="{{ old('name', $client->name) }}">
                <x-input-error :messages="$errors->get('name')" class="mt-2" />
            </div>
            <div>
                <x-input-label for="company_name" value="Company name" />
                <input id="company_name" name="company_name" type="text" maxlength="255" class="{{ $input }}" value="{{ old('company_name', $client->company_name) }}">
                <x-input-error :messages="$errors->get('company_name')" class="mt-2" />
            </div>
            <div>
                <x-input-label for="email" value="Email" />
                <input id="email" name="email" type="email" maxlength="255" class="{{ $input }}" value="{{ old('email', $client->email) }}">
                <x-input-error :messages="$errors->get('email')" class="mt-2" />
            </div>
            <div>
                <x-input-label for="phone" value="Phone" />
                <input id="phone" name="phone" type="tel" maxlength="30" class="{{ $input }}" value="{{ old('phone', $client->phone) }}">
                <x-input-error :messages="$errors->get('phone')" class="mt-2" />
            </div>
            @include('clients._address-fields')
        </div>
    </x-card>

    <x-card title="Account">
        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
            <div>
                <x-input-label for="account_manager_id" value="Account manager *" />
                <select id="account_manager_id" name="account_manager_id" required class="{{ $input }}">
                    <option value="">Select…</option>
                    @foreach ($managers as $m)
                        <option value="{{ $m->id }}" @selected(old('account_manager_id', $client->account_manager_id ?? auth()->id()) == $m->id)>{{ $m->name }}</option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('account_manager_id')" class="mt-2" />
            </div>
            <div>
                <x-input-label for="status" value="Status *" />
                <select id="status" name="status" required class="{{ $input }}">
                    @foreach (App\Models\Client::STATUSES as $value => $label)
                        <option value="{{ $value }}" @selected(old('status', $client->status ?? 'active') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('status')" class="mt-2" />
            </div>
            <div class="sm:col-span-2">
                <x-input-label for="notes" value="Notes" />
                <textarea id="notes" name="notes" rows="3" maxlength="5000" class="{{ $input }}">{{ old('notes', $client->notes) }}</textarea>
                <x-input-error :messages="$errors->get('notes')" class="mt-2" />
            </div>
        </div>
    </x-card>

    <div class="flex items-center gap-4">
        <x-primary-button>Save client</x-primary-button>
        <a href="{{ $client->exists ? route('clients.show', $client) : route('clients.index') }}" class="text-sm text-slate-600 hover:underline">Cancel</a>
    </div>
</form>
