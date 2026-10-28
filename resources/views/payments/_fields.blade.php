{{-- Shared fields for recording and correcting a payment. Expects $methods, optional $defaults. --}}
@php
    $input = 'mt-1 block w-full border-slate-300 rounded-lg shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500';
    $defaults ??= [];
@endphp

<div>
    <x-input-label for="amount" value="Amount *" />
    <input id="amount" name="amount" type="text" inputmode="decimal" required class="{{ $input }} text-right" value="{{ old('amount', $defaults['amount'] ?? '') }}" x-model="amount">
    <x-input-error :messages="$errors->get('amount')" class="mt-1" />
</div>
<div>
    <x-input-label for="payment_date" value="Payment date *" />
    <input id="payment_date" name="payment_date" type="date" required max="{{ now()->toDateString() }}" class="{{ $input }}" value="{{ old('payment_date', $defaults['payment_date'] ?? now()->toDateString()) }}">
    <x-input-error :messages="$errors->get('payment_date')" class="mt-1" />
</div>
<div>
    <x-input-label for="method" value="Method *" />
    <select id="method" name="method" required class="{{ $input }}">
        @foreach ($methods as $m)
            <option value="{{ $m }}" @selected(old('method', $defaults['method'] ?? null) === $m)>{{ $m }}</option>
        @endforeach
    </select>
    <x-input-error :messages="$errors->get('method')" class="mt-1" />
</div>
<div>
    <x-input-label for="reference" value="Reference no." />
    <input id="reference" name="reference" type="text" maxlength="100" class="{{ $input }}" value="{{ old('reference', $defaults['reference'] ?? '') }}" placeholder="UTR, cheque no., transaction ID">
    <p class="mt-1 text-xs text-slate-500">Never enter full card numbers.</p>
    <x-input-error :messages="$errors->get('reference')" class="mt-1" />
</div>
<div class="sm:col-span-2">
    <x-input-label for="notes" value="Notes" />
    <textarea id="notes" name="notes" rows="2" maxlength="2000" class="{{ $input }}">{{ old('notes', $defaults['notes'] ?? '') }}</textarea>
    <x-input-error :messages="$errors->get('notes')" class="mt-1" />
</div>
