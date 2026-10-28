<?php

namespace App\Http\Controllers;

use App\Services\Invoicing\InvoiceNumberGenerator;
use App\Support\Audit;
use App\Support\Gstin;
use App\Support\Money;
use App\Support\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SettingsController extends Controller
{
    public function edit(InvoiceNumberGenerator $numbers): View
    {
        return view('settings.edit', [
            'settings' => Settings::all(),
            'nextNumber' => $numbers->preview(today()),
            'currencies' => Money::CURRENCIES,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $request->merge([
            'tax_registration_number' => strtoupper(trim((string) $request->input('tax_registration_number'))) ?: null,
            'company_state_code' => strtoupper(trim((string) $request->input('company_state_code'))) ?: null,
            'invoice_prefix' => strtoupper(trim((string) $request->input('invoice_prefix'))),
        ]);

        $data = $request->validate([
            'company_name' => ['required', 'string', 'max:255'],
            'company_legal_name' => ['nullable', 'string', 'max:255'],
            'company_address' => ['nullable', 'string', 'max:1000'],
            'company_city' => ['nullable', 'string', 'max:100'],
            'company_state' => ['nullable', 'string', 'max:100'],
            'company_state_code' => [Rule::requiredIf($request->input('tax_system') === 'gst'), 'nullable', 'string', 'max:5', 'regex:/^[0-9A-Z]{1,5}$/'],
            'company_postal_code' => ['nullable', 'string', 'max:20'],
            'company_country' => ['nullable', 'string', 'max:100'],
            'company_email' => ['nullable', 'email', 'max:255'],
            'company_phone' => ['nullable', 'string', 'max:30'],
            'company_website' => ['nullable', 'url', 'max:255'],

            'tax_system' => ['required', Rule::in(['gst', 'simple', 'none'])],
            'tax_label' => ['required', 'string', 'max:30'],
            'tax_registration_label' => ['required', 'string', 'max:30'],
            'tax_registration_number' => ['nullable', 'string', 'max:30', Rule::when($request->input('tax_system') === 'gst' && $request->filled('tax_registration_number'), ['regex:'.Gstin::PATTERN])],
            'default_tax_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'tax_rates' => ['required', 'string', 'max:200'],

            'invoice_prefix' => ['required', 'string', 'max:20', 'regex:/^[A-Z0-9\/]+$/'],
            'invoice_year_format' => ['required', Rule::in(['Y', 'FY', 'none'])],
            'invoice_number_padding' => ['required', 'integer', 'min:1', 'max:8'],
            'financial_year_start_month' => ['required', 'integer', 'min:1', 'max:12'],
            'currency' => ['required', Rule::in(Money::CURRENCIES)],
            'payment_terms_days' => ['required', 'integer', 'min:0', 'max:365'],
            'payment_terms_text' => ['nullable', 'string', 'max:255'],
            'payment_instructions' => ['nullable', 'string', 'max:2000'],
            'invoice_terms' => ['nullable', 'string', 'max:5000'],
            'invoice_footer' => ['nullable', 'string', 'max:500'],
            'signatory_name' => ['nullable', 'string', 'max:255'],

            'payment_methods' => ['required', 'string', 'max:500'],
            'due_reminder_days' => ['required', 'integer', 'min:0', 'max:60'],

            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg', 'max:1024'],
            'signature' => ['nullable', 'image', 'mimes:png,jpg,jpeg', 'max:1024'],
            'remove_logo' => ['nullable', 'boolean'],
            'remove_signature' => ['nullable', 'boolean'],
        ], [
            'company_state_code.required' => 'Your GST state code is required to decide between CGST/SGST and IGST.',
            'tax_registration_number.regex' => 'Enter a valid 15-character GSTIN.',
            'invoice_prefix.regex' => 'Use letters, numbers or "/" only.',
        ]);

        $rates = $this->parseList($data['tax_rates'], numeric: true);
        if (! $rates) {
            return back()->withErrors(['tax_rates' => 'Enter at least one tax rate, e.g. 0, 5, 12, 18, 28.'])->withInput();
        }
        // The default rate is always one of the selectable rates.
        $data['default_tax_rate'] = $this->parseList((string) $data['default_tax_rate'], numeric: true)[0];
        $rates = array_values(array_unique([...$rates, $data['default_tax_rate']]));
        sort($rates, SORT_NUMERIC);
        $data['tax_rates'] = $rates;
        $data['payment_methods'] = $this->parseList($data['payment_methods']);

        foreach (['logo' => 'company_logo_path', 'signature' => 'signature_path'] as $field => $key) {
            if ($request->boolean("remove_$field") || $request->hasFile($field)) {
                if ($old = Settings::get($key)) {
                    Storage::disk('local')->delete($old);
                }
                $data[$key] = null;
            }
            if ($request->hasFile($field)) {
                $data[$key] = $request->file($field)->store('branding', 'local');
            }
            unset($data[$field], $data["remove_$field"]);
        }

        $before = Settings::all();
        Settings::put($data);
        $changed = array_keys(array_filter($data, fn ($v, $k) => ($before[$k] ?? null) != $v, ARRAY_FILTER_USE_BOTH));

        if ($changed) {
            Audit::log('settings.updated', null, 'Company, tax or invoice settings updated', ['fields' => $changed]);
        }

        return redirect()->route('settings.edit')->with('status', 'Settings saved. New invoices will use them; issued invoices keep their original details.');
    }

    /** Serve the uploaded logo/signature to signed-in users (no public storage link needed). */
    public function branding(string $type): StreamedResponse
    {
        $path = Settings::get($type === 'signature' ? 'signature_path' : 'company_logo_path');
        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path, null, ['Cache-Control' => 'private, max-age=3600']);
    }

    /** @return list<string> */
    private function parseList(string $value, bool $numeric = false): array
    {
        $items = collect(preg_split('/[,\n]+/', $value))->map(fn ($v) => trim($v))->filter(fn ($v) => $v !== '');

        if ($numeric) {
            $items = $items->filter(fn ($v) => is_numeric($v) && $v >= 0 && $v <= 100)->map(fn ($v) => rtrim(rtrim(number_format((float) $v, 2, '.', ''), '0'), '.'));
        }

        return $items->unique()->values()->all();
    }
}
