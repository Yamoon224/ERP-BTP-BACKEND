<?php

namespace App\Domains\Procurement\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'code' => [
                'sometimes', 'string', 'max:50',
                Rule::unique('projects', 'code')->ignore($this->route('project')->id),
            ],
            'name' => ['sometimes', 'string', 'max:255'],
            'client_name' => ['nullable', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
