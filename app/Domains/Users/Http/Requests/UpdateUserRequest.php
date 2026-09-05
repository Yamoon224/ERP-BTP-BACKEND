<?php

namespace App\Domains\Users\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => [
                'sometimes', 'email', 'max:255',
                Rule::unique('users', 'email')->ignore($this->route('user')->id),
            ],
            // Absent ou vide : le mot de passe actuel est conserve.
            'password' => ['nullable', 'string', Password::min(8)],
            'roles' => ['sometimes', 'array', 'min:1'],
            'roles.*' => ['string', Rule::exists('roles', 'name')],
        ];
    }
}
