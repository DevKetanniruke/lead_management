<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ClientRules;
use App\Models\Lead;
use App\Support\PurchasedServices;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ConversionRequest extends FormRequest
{
    use ClientRules;

    /**
     * Authorise before validating, so ineligible users get a 403 rather than field errors.
     * An already-converted lead passes through: the controller redirects to its client.
     */
    public function authorize(): bool
    {
        return $this->lead()->isConverted() || $this->user()->can('convert', $this->lead());
    }

    protected function prepareForValidation(): void
    {
        $this->normaliseClientInput();
    }

    public function rules(): array
    {
        // Repeated submit after a successful conversion: nothing to validate, nothing will be written.
        if ($this->lead()->isConverted()) {
            return [];
        }

        $link = $this->input('mode') === 'link';

        // When linking, client details come from the existing client; only deal data is needed.
        $client = $link ? [] : $this->clientRules();

        // Users who cannot assign work become the account manager themselves (set in the controller).
        if (! $link && ! $this->user()->can('leads.assign')) {
            $client['account_manager_id'] = ['nullable'];
        }

        return $client + [
            'mode' => ['required', 'in:new,link'],
            'client_id' => ['required_if:mode,link', 'nullable', 'integer', 'exists:clients,id'],
            'converted_at' => ['required', 'date', 'before_or_equal:today'],
            'service' => ['required', Rule::in(PurchasedServices::OPTIONS)],
            'custom_service' => ['required_if:service,Other', 'nullable', 'string', 'max:255'],
            'deal_value' => ['nullable', 'numeric', 'min:0', 'max:9999999999999'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    public function messages(): array
    {
        return $this->clientMessages() + [
            'client_id.required_if' => 'Choose the existing client to link this lead to.',
            'service.required' => 'Choose the service or product purchased.',
            'custom_service.required_if' => 'Describe the service or product purchased.',
            'converted_at.before_or_equal' => 'The conversion date cannot be in the future.',
        ];
    }

    /** Return validated conversion data with a custom "Other" selection normalized for storage. */
    public function conversionData(): array
    {
        $data = $this->validated();
        if (($data['service'] ?? null) === 'Other') {
            $data['service'] = trim($data['custom_service']);
        }
        unset($data['custom_service']);

        return $data;
    }

    private function lead(): Lead
    {
        return $this->route('lead');
    }
}
