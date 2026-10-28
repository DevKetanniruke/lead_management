@php $input = 'mt-1 block w-full border-slate-300 rounded-lg shadow-sm focus:border-indigo-500 focus:ring-indigo-500'; @endphp

<div class="sm:col-span-2">
    <x-input-label for="address" value="Address" />
    <textarea id="address" name="address" rows="2" maxlength="1000" class="{{ $input }}">{{ old('address', $client->address) }}</textarea>
    <x-input-error :messages="$errors->get('address')" class="mt-2" />
</div>
<div>
    <x-input-label for="city" value="City" />
    <input id="city" name="city" type="text" maxlength="100" class="{{ $input }}" value="{{ old('city', $client->city) }}">
    <x-input-error :messages="$errors->get('city')" class="mt-2" />
</div>
<div>
    <x-input-label for="postal_code" value="Postal code" />
    <input id="postal_code" name="postal_code" type="text" maxlength="20" class="{{ $input }}" value="{{ old('postal_code', $client->postal_code) }}">
    <x-input-error :messages="$errors->get('postal_code')" class="mt-2" />
</div>
<div>
    <x-input-label for="state" value="State" />
    <input id="state" name="state" type="text" maxlength="100" class="{{ $input }}" value="{{ old('state', $client->state) }}">
    <x-input-error :messages="$errors->get('state')" class="mt-2" />
</div>
<div>
    <x-input-label for="state_code" value="GST state code" />
    <input id="state_code" name="state_code" type="text" maxlength="5" class="{{ $input }}" value="{{ old('state_code', $client->state_code) }}" placeholder="e.g. 27 (filled from GSTIN)">
    <p class="mt-1 text-xs text-slate-500">Decides CGST + SGST (same state) or IGST (other state) on invoices.</p>
    <x-input-error :messages="$errors->get('state_code')" class="mt-2" />
</div>
<div>
    <x-input-label for="country" value="Country" />
    <input id="country" name="country" type="text" maxlength="100" class="{{ $input }}" value="{{ old('country', $client->country ?? 'India') }}">
    <x-input-error :messages="$errors->get('country')" class="mt-2" />
</div>
<div>
    <x-input-label for="gstin" value="Tax / GST number (GSTIN)" />
    <input id="gstin" name="gstin" type="text" maxlength="20" class="{{ $input }} uppercase" value="{{ old('gstin', $client->gstin) }}" placeholder="27ABCDE1234F1Z5">
    <x-input-error :messages="$errors->get('gstin')" class="mt-2" />
</div>
