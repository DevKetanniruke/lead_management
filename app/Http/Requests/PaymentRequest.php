<?php

namespace App\Http\Requests;

use App\Support\Settings;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // PaymentPolicy / InvoicePolicy are checked in the controller
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'amount' => str_replace([',', ' '], '', (string) $this->input('amount')),
            'reference' => trim((string) $this->input('reference')) ?: null,
        ]);
    }

    public function rules(): array
    {
        return [
            'amount' => ['required', 'regex:/^\d{1,13}(\.\d{1,2})?$/', 'not_regex:/^0+(\.0+)?$/'],
            'payment_date' => ['required', 'date', 'before_or_equal:today'],
            'method' => ['required', 'string', Rule::in(Settings::get('payment_methods', []))],
            // Never store full card numbers: reject anything that looks like a 13–19 digit PAN.
            'reference' => ['nullable', 'string', 'max:100', 'not_regex:/(?:\d[ -]?){13,19}/'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'amount.regex' => 'Enter a positive amount with up to 2 decimals.',
            'amount.not_regex' => 'The payment amount must be greater than zero.',
            'payment_date.before_or_equal' => 'The payment date cannot be in the future.',
            'method.in' => 'Choose one of the configured payment methods.',
            'reference.not_regex' => 'Do not enter card numbers. Use the last 4 digits or the transaction ID instead.',
        ];
    }
}
