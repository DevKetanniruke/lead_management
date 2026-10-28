<?php

namespace App\Http\Requests\Concerns;

use App\Models\Client;
use App\Support\Gstin;
use Illuminate\Validation\Rule;

/** Validation shared by manual client forms and the lead conversion form. */
trait ClientRules
{
    protected function clientRules(?Client $ignore = null, bool $required = true): array
    {
        $unique = fn (string $column) => Rule::unique('clients', $column)->ignore($ignore?->id);

        return [
            'name' => [$required ? 'required' : 'nullable', 'string', 'max:255'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255', $unique('email')],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:1000'],
            'city' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
            'state_code' => ['nullable', 'string', 'max:5', 'regex:/^[0-9A-Z]{1,5}$/'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'country' => ['nullable', 'string', 'max:100'],
            'gstin' => ['nullable', 'string', 'regex:'.Gstin::PATTERN, $unique('gstin')],
            'account_manager_id' => [$required ? 'required' : 'nullable', Rule::exists('users', 'id')->where(
                fn ($q) => $q->where('is_active', true)->orWhere('id', $ignore?->account_manager_id)
            )],
        ];
    }

    /** Uppercase tax IDs and derive the GST state code from the GSTIN when not given. */
    protected function normaliseClientInput(): void
    {
        $gstin = strtoupper(preg_replace('/\s+/', '', (string) $this->input('gstin')));

        $this->merge([
            'gstin' => $gstin ?: null,
            'state_code' => strtoupper(trim((string) $this->input('state_code'))) ?: (preg_match('/^[0-9]{2}/', $gstin) ? substr($gstin, 0, 2) : null),
            'country' => trim((string) $this->input('country')) ?: 'India',
        ]);
    }

    protected function clientMessages(): array
    {
        return [
            'email.unique' => 'A client with this email already exists. Link to that client instead of creating a duplicate.',
            'gstin.unique' => 'A client with this GSTIN already exists. Link to that client instead of creating a duplicate.',
            'gstin.regex' => 'Enter a valid 15-character GSTIN (e.g. 27ABCDE1234F1Z5).',
            'state_code.regex' => 'Use the numeric GST state code, e.g. 27 for Maharashtra.',
        ];
    }
}
