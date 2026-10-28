<?php

namespace App\Http\Requests;

use App\Support\Permissions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // route is behind the users.manage permission middleware
    }

    public function rules(): array
    {
        $user = $this->route('user');
        $sendLink = $this->boolean('send_setup_link');

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user?->id)],
            'phone' => ['nullable', 'string', 'max:30'],
            'department' => ['nullable', 'string', 'max:100'],
            'password' => [$user || $sendLink ? 'nullable' : 'required', Password::min(8)],
            'role' => ['required', Rule::in(array_keys(Permissions::ROLES))],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'remove_photo' => ['nullable', 'boolean'],
            'send_setup_link' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.unique' => 'A user with this email address already exists.',
            'password.required' => 'Set a password or choose to email a password setup link.',
        ];
    }
}
