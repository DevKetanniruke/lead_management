<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ClientRules;
use App\Models\Client;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ClientRequest extends FormRequest
{
    use ClientRules;

    public function authorize(): bool
    {
        return true; // ClientPolicy is checked in the controller
    }

    protected function prepareForValidation(): void
    {
        $this->normaliseClientInput();
    }

    public function rules(): array
    {
        return $this->clientRules($this->route('client')) + [
            'status' => ['required', Rule::in(array_keys(Client::STATUSES))],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    public function messages(): array
    {
        return $this->clientMessages();
    }
}
