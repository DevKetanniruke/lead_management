@php
    $input = 'mt-1 block w-full border-slate-300 rounded-lg shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500';
    $s = $settings;
    $months = collect(range(1, 12))->mapWithKeys(fn ($m) => [$m => \Carbon\Carbon::create(2000, $m, 1)->format('F')]);
@endphp

<x-app-layout title="Settings">
    <x-slot name="header">
        <h2 class="truncate text-lg font-semibold text-slate-900">Settings</h2>
    </x-slot>

    <form method="POST" action="{{ route('settings.update') }}" enctype="multipart/form-data" class="mx-auto max-w-5xl space-y-6"
          x-data="{ taxSystem: @js(old('tax_system', $s['tax_system'])), prefix: @js(old('invoice_prefix', $s['invoice_prefix'])), year: @js(old('invoice_year_format', $s['invoice_year_format'])), pad: {{ (int) old('invoice_number_padding', $s['invoice_number_padding']) }} }">
        @csrf @method('PUT')
        <x-flash />

        <x-card title="Company">
            <p class="-mt-1 mb-5 text-sm text-slate-500">Shown on invoices and emails. Issued invoices keep the details they were issued with.</p>
            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                @foreach ([
                    'company_name' => ['Company name *', 'text'],
                    'company_legal_name' => ['Legal / registered name', 'text'],
                    'company_email' => ['Billing email', 'email'],
                    'company_phone' => ['Phone', 'text'],
                    'company_website' => ['Website', 'url'],
                    'company_city' => ['City', 'text'],
                    'company_state' => ['State', 'text'],
                    'company_postal_code' => ['Postal code', 'text'],
                    'company_country' => ['Country', 'text'],
                ] as $key => [$label, $type])
                    <div>
                        <x-input-label :for="$key" :value="$label" />
                        <input id="{{ $key }}" name="{{ $key }}" type="{{ $type }}" class="{{ $input }}" value="{{ old($key, $s[$key]) }}">
                        <x-input-error :messages="$errors->get($key)" class="mt-1" />
                    </div>
                @endforeach
                <div>
                    <x-input-label for="company_state_code" value="GST state code" />
                    <input id="company_state_code" name="company_state_code" type="text" maxlength="5" class="{{ $input }}" value="{{ old('company_state_code', $s['company_state_code']) }}" placeholder="e.g. 27 (Maharashtra)">
                    <p class="mt-1 text-xs text-slate-500">Invoices to the same state get CGST + SGST; other states get IGST.</p>
                    <x-input-error :messages="$errors->get('company_state_code')" class="mt-1" />
                </div>
                <div class="sm:col-span-2">
                    <x-input-label for="company_address" value="Address" />
                    <textarea id="company_address" name="company_address" rows="2" class="{{ $input }}">{{ old('company_address', $s['company_address']) }}</textarea>
                </div>
                <div class="sm:col-span-2">
                    <x-input-label for="logo" value="Logo (PNG or JPG, max 1 MB)" />
                    <div class="mt-1 flex flex-wrap items-center gap-4">
                        @if ($s['company_logo_path'])
                            <img src="{{ route('settings.branding', 'logo') }}" alt="Current logo" class="h-12 max-w-[10rem] rounded border border-slate-200 bg-white object-contain p-1">
                            <label class="flex items-center gap-2 text-sm text-slate-600"><input type="checkbox" name="remove_logo" value="1" class="rounded border-slate-300 text-indigo-600"> Remove</label>
                        @endif
                        <input id="logo" name="logo" type="file" accept="image/png,image/jpeg" class="text-sm text-slate-600 file:me-3 file:rounded-lg file:border-0 file:bg-indigo-50 file:px-3 file:py-2 file:text-sm file:font-medium file:text-indigo-700">
                    </div>
                    <x-input-error :messages="$errors->get('logo')" class="mt-1" />
                </div>
            </div>
        </x-card>

        <x-card title="Tax">
            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <x-input-label value="Tax system *" />
                    <div class="mt-2 grid gap-2 sm:grid-cols-3">
                        @foreach (['gst' => ['India GST', 'CGST + SGST or IGST by place of supply'], 'simple' => ['Single tax', 'One tax line, e.g. VAT or sales tax'], 'none' => ['No tax', 'Invoices without tax']] as $value => [$label, $hint])
                            <label class="flex cursor-pointer items-start gap-2 rounded-lg border p-3 text-sm" :class="taxSystem === '{{ $value }}' ? 'border-indigo-400 bg-indigo-50' : 'border-slate-200'">
                                <input type="radio" name="tax_system" value="{{ $value }}" x-model="taxSystem" class="mt-0.5 text-indigo-600">
                                <span><span class="font-medium text-slate-900">{{ $label }}</span><br><span class="text-slate-500">{{ $hint }}</span></span>
                            </label>
                        @endforeach
                    </div>
                </div>
                <div>
                    <x-input-label for="tax_registration_label" value="Registration label *" />
                    <input id="tax_registration_label" name="tax_registration_label" type="text" class="{{ $input }}" value="{{ old('tax_registration_label', $s['tax_registration_label']) }}">
                </div>
                <div>
                    <x-input-label for="tax_registration_number" value="Registration number (your GSTIN)" />
                    <input id="tax_registration_number" name="tax_registration_number" type="text" class="{{ $input }} uppercase" value="{{ old('tax_registration_number', $s['tax_registration_number']) }}">
                    <x-input-error :messages="$errors->get('tax_registration_number')" class="mt-1" />
                </div>
                <div x-show="taxSystem === 'simple'">
                    <x-input-label for="tax_label" value="Tax name on invoices *" />
                    <input id="tax_label" name="tax_label" type="text" class="{{ $input }}" value="{{ old('tax_label', $s['tax_label']) }}" placeholder="e.g. VAT">
                </div>
                <div>
                    <x-input-label for="tax_rates" value="Available tax rates (%) *" />
                    <input id="tax_rates" name="tax_rates" type="text" class="{{ $input }}" value="{{ old('tax_rates', implode(', ', $s['tax_rates'])) }}" placeholder="0, 5, 12, 18, 28">
                    <x-input-error :messages="$errors->get('tax_rates')" class="mt-1" />
                </div>
                <div>
                    <x-input-label for="default_tax_rate" value="Default tax rate (%) *" />
                    <input id="default_tax_rate" name="default_tax_rate" type="number" step="0.01" min="0" max="100" class="{{ $input }}" value="{{ old('default_tax_rate', $s['default_tax_rate']) }}">
                    <x-input-error :messages="$errors->get('default_tax_rate')" class="mt-1" />
                </div>
            </div>
        </x-card>

        <x-card title="Invoice numbering">
            <div class="grid grid-cols-1 gap-5 sm:grid-cols-4">
                <div>
                    <x-input-label for="invoice_prefix" value="Prefix *" />
                    <input id="invoice_prefix" name="invoice_prefix" type="text" maxlength="20" class="{{ $input }} uppercase" x-model="prefix">
                    <x-input-error :messages="$errors->get('invoice_prefix')" class="mt-1" />
                </div>
                <div>
                    <x-input-label for="invoice_year_format" value="Year segment *" />
                    <select id="invoice_year_format" name="invoice_year_format" class="{{ $input }}" x-model="year">
                        <option value="Y">Calendar year (2026)</option>
                        <option value="FY">Financial year (2026-27)</option>
                        <option value="none">None</option>
                    </select>
                </div>
                <div>
                    <x-input-label for="invoice_number_padding" value="Digits *" />
                    <input id="invoice_number_padding" name="invoice_number_padding" type="number" min="1" max="8" class="{{ $input }}" x-model.number="pad">
                </div>
                <div>
                    <x-input-label for="financial_year_start_month" value="Financial year starts" />
                    <select id="financial_year_start_month" name="financial_year_start_month" class="{{ $input }}">
                        @foreach ($months as $m => $name)
                            <option value="{{ $m }}" @selected(old('financial_year_start_month', $s['financial_year_start_month']) == $m)>{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <p class="mt-4 text-sm text-slate-600">
                Example: <code class="rounded bg-slate-100 px-1.5 py-0.5 font-mono" x-text="[prefix.toUpperCase() || 'INV', year === 'Y' ? '{{ now()->year }}' : (year === 'FY' ? 'YYYY-YY' : null), '1'.padStart(pad, '0')].filter(Boolean).join('-')"></code>
                · Next number with saved settings: <code class="rounded bg-slate-100 px-1.5 py-0.5 font-mono">{{ $nextNumber }}</code>.
                Numbers are never reused, even if a draft is deleted.
            </p>
        </x-card>

        <x-card title="Invoice defaults">
            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                <div>
                    <x-input-label for="currency" value="Default currency *" />
                    <select id="currency" name="currency" class="{{ $input }}">
                        @foreach ($currencies as $code)<option value="{{ $code }}" @selected(old('currency', $s['currency']) === $code)>{{ $code }}</option>@endforeach
                    </select>
                </div>
                <div>
                    <x-input-label for="payment_terms_days" value="Payment due after (days) *" />
                    <input id="payment_terms_days" name="payment_terms_days" type="number" min="0" max="365" class="{{ $input }}" value="{{ old('payment_terms_days', $s['payment_terms_days']) }}">
                </div>
                <div class="sm:col-span-2">
                    <x-input-label for="payment_terms_text" value="Payment terms text" />
                    <input id="payment_terms_text" name="payment_terms_text" type="text" class="{{ $input }}" value="{{ old('payment_terms_text', $s['payment_terms_text']) }}">
                </div>
                <div>
                    <x-input-label for="payment_instructions" value="Payment instructions (bank details, UPI…)" />
                    <textarea id="payment_instructions" name="payment_instructions" rows="4" class="{{ $input }}" placeholder="Bank: …&#10;A/c no: …&#10;IFSC: …&#10;UPI: …">{{ old('payment_instructions', $s['payment_instructions']) }}</textarea>
                </div>
                <div>
                    <x-input-label for="invoice_terms" value="Default terms & conditions" />
                    <textarea id="invoice_terms" name="invoice_terms" rows="4" class="{{ $input }}">{{ old('invoice_terms', $s['invoice_terms']) }}</textarea>
                </div>
                <div>
                    <x-input-label for="invoice_footer" value="Footer line" />
                    <input id="invoice_footer" name="invoice_footer" type="text" class="{{ $input }}" value="{{ old('invoice_footer', $s['invoice_footer']) }}">
                </div>
                <div>
                    <x-input-label for="signatory_name" value="Authorised signatory (optional)" />
                    <input id="signatory_name" name="signatory_name" type="text" class="{{ $input }}" value="{{ old('signatory_name', $s['signatory_name']) }}">
                </div>
                <div class="sm:col-span-2">
                    <x-input-label for="signature" value="Signature image (optional, PNG or JPG)" />
                    <div class="mt-1 flex flex-wrap items-center gap-4">
                        @if ($s['signature_path'])
                            <img src="{{ route('settings.branding', 'signature') }}" alt="Current signature" class="h-10 max-w-[10rem] rounded border border-slate-200 bg-white object-contain p-1">
                            <label class="flex items-center gap-2 text-sm text-slate-600"><input type="checkbox" name="remove_signature" value="1" class="rounded border-slate-300 text-indigo-600"> Remove</label>
                        @endif
                        <input id="signature" name="signature" type="file" accept="image/png,image/jpeg" class="text-sm text-slate-600 file:me-3 file:rounded-lg file:border-0 file:bg-indigo-50 file:px-3 file:py-2 file:text-sm file:font-medium file:text-indigo-700">
                    </div>
                    <x-input-error :messages="$errors->get('signature')" class="mt-1" />
                </div>
            </div>
        </x-card>

        <x-card title="Payments & reminders">
            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                <div>
                    <x-input-label for="payment_methods" value="Payment methods *" />
                    <input id="payment_methods" name="payment_methods" type="text" class="{{ $input }}" value="{{ old('payment_methods', implode(', ', $s['payment_methods'])) }}">
                    <p class="mt-1 text-xs text-slate-500">Comma separated, e.g. Bank Transfer, UPI, Cash, Card, Cheque, Other.</p>
                    <x-input-error :messages="$errors->get('payment_methods')" class="mt-1" />
                </div>
                <div>
                    <x-input-label for="due_reminder_days" value="Remind account managers this many days before due *" />
                    <input id="due_reminder_days" name="due_reminder_days" type="number" min="0" max="60" class="{{ $input }}" value="{{ old('due_reminder_days', $s['due_reminder_days']) }}">
                </div>
            </div>
        </x-card>

        <div class="flex items-center gap-4">
            <x-primary-button>Save settings</x-primary-button>
        </div>
    </form>
</x-app-layout>
