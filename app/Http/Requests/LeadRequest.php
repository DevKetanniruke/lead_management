<?php

namespace App\Http\Requests;

use App\Models\LeadSource;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LeadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // authorization is handled by LeadPolicy in the controller
    }

    public function rules(): array
    {
        $lead = $this->route('lead');

        $unique = fn (string $column) => Rule::unique('leads', $column)
            ->whereNull('deleted_at')
            ->ignore($lead?->id);

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255', $unique('email')],
            'phone' => ['nullable', 'string', 'max:30', $unique('phone')],
            'company' => ['nullable', 'string', 'max:255'],
            'lead_source_id' => ['nullable', 'exists:lead_sources,id'],
            'lead_source_other' => [
                Rule::requiredIf(fn () => $this->isOtherSource()),
                'nullable', 'string', 'max:255',
            ],
            'lead_status_id' => ['required', 'exists:lead_statuses,id'],
            // Active users only; an existing (now inactive) assignee may be kept unchanged.
            'assigned_to' => ['nullable', Rule::exists('users', 'id')->where(
                fn ($q) => $q->where('is_active', true)->orWhere('id', $lead?->assigned_to)
            )],
            'value' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'next_follow_up_at' => ['nullable', 'date'],
            'client_id'         => ['nullable', 'exists:clients,id'],
        ];
    }

    /** The custom text only applies when the "Other" source is selected; clear it otherwise. */
    public function validated($key = null, $default = null)
    {
        $data = parent::validated();

        if (! $this->isOtherSource()) {
            $data['lead_source_other'] = null;
        }

        return $key ? data_get($data, $key, $default) : $data;
    }

    private function isOtherSource(): bool
    {
        $id = $this->input('lead_source_id');

        return $id && LeadSource::whereKey($id)->where('name', LeadSource::OTHER)->exists();
    }

    public function messages(): array
    {
        return [
            'lead_source_other.required' => 'Please specify the source.',
            'email.unique' => 'A lead with this email already exists.',
            'phone.unique' => 'A lead with this phone number already exists.',
        ];
    }
}
