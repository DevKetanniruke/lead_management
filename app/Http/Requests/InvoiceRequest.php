<?php

namespace App\Http\Requests;

use App\Models\Client;
use App\Support\Money;
use App\Support\Settings;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class InvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // InvoicePolicy is checked in the controller
    }

    protected function prepareForValidation(): void
    {
        // Drop blank rows (the tax select always has a value, so it is ignored), trim, default discount/tax to 0.
        $items = collect($this->input('items', []))
            ->filter(fn ($row) => is_array($row) && collect($row)->except('tax_rate')->filter(fn ($v) => filled($v))->isNotEmpty())
            ->map(function (array $row) {
                $row = array_map(fn ($v) => is_string($v) ? trim($v) : $v, $row);
                $row['discount'] = filled($row['discount'] ?? null) ? $row['discount'] : '0';
                $row['tax_rate'] = filled($row['tax_rate'] ?? null) ? $row['tax_rate'] : '0';

                return $row;
            })
            ->values()->all();

        $this->merge([
            'items' => $items,
            'place_of_supply' => strtoupper(trim((string) $this->input('place_of_supply'))) ?: null,
            'currency' => strtoupper((string) $this->input('currency')),
        ]);
    }

    public function rules(): array
    {
        $decimal = 'regex:/^\d{1,13}(\.\d{1,2})?$/';

        return [
            'client_id' => ['required', 'integer', Rule::exists('clients', 'id')],
            'lead_id' => ['nullable', 'integer', Rule::exists('leads', 'id')],
            'invoice_date' => ['required', 'date'],
            'due_date' => ['required', 'date', 'after_or_equal:invoice_date'],
            'currency' => ['required', Rule::in(Money::CURRENCIES)],
            'place_of_supply' => [Rule::requiredIf(Settings::get('tax_system') === 'gst'), 'nullable', 'string', 'max:5', 'regex:/^[0-9A-Z]{1,5}$/'],
            'payment_terms' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'internal_notes' => ['nullable', 'string', 'max:5000'],
            'terms' => ['nullable', 'string', 'max:10000'],

            'items' => ['required', 'array', 'min:1', 'max:200'],
            'items.*.description' => ['required', 'string', 'max:500'],
            'items.*.hsn_sac' => ['nullable', 'string', 'max:20'],
            'items.*.quantity' => ['required', 'regex:/^\d{1,9}(\.\d{1,3})?$/', 'not_regex:/^0+(\.0+)?$/'],
            'items.*.unit_price' => ['required', $decimal],
            'items.*.discount' => ['nullable', $decimal],
            'items.*.tax_rate' => ['nullable', 'regex:/^\d{1,2}(\.\d{1,2})?$|^100(\.0{1,2})?$/'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            // Discount cannot exceed the line amount (checked with decimal arithmetic).
            foreach ($this->input('items', []) as $i => $item) {
                if ($validator->errors()->has("items.$i.quantity") || $validator->errors()->has("items.$i.unit_price") || $validator->errors()->has("items.$i.discount")) {
                    continue;
                }
                $lineAmount = Money::mul($item['quantity'], $item['unit_price']);
                if (Money::cmp($item['discount'] ?? '0', $lineAmount) > 0) {
                    $validator->errors()->add("items.$i.discount", 'Line '.($i + 1).': the discount cannot exceed the line amount ('.$lineAmount.').');
                }
            }

            if (Settings::get('tax_system') === 'gst' && ! Settings::get('company_state_code')) {
                $validator->errors()->add('place_of_supply', 'Set your company GST state code in Settings before creating GST invoices.');
            }

            $client = Client::find($this->input('client_id'));
            if ($client && $client->status === 'archived') {
                $validator->errors()->add('client_id', 'This client is archived. Reactivate it before invoicing.');
            }
        }];
    }

    public function messages(): array
    {
        return [
            'items.required' => 'Add at least one line item.',
            'items.*.description.required' => 'Each line needs a description.',
            'items.*.quantity.regex' => 'Quantity must be a positive number (up to 3 decimals).',
            'items.*.quantity.not_regex' => 'Quantity must be greater than zero.',
            'items.*.unit_price.regex' => 'Unit price must be a non-negative amount with up to 2 decimals.',
            'items.*.discount.regex' => 'Discount must be a non-negative amount with up to 2 decimals.',
            'items.*.tax_rate.regex' => 'Tax rate must be between 0 and 100.',
            'due_date.after_or_equal' => 'The due date cannot be before the invoice date.',
            'place_of_supply.required' => 'Place of supply (GST state code) is required for GST invoices.',
        ];
    }

    /** @return array{0: array<string, mixed>, 1: list<array<string, mixed>>} header data and items */
    public function invoiceData(): array
    {
        $data = $this->validated();
        $items = $data['items'];
        unset($data['items']);

        return [$data, $items];
    }
}
