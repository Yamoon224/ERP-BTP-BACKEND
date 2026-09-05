<?php

namespace App\Domains\Procurement\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSupplierRequest extends FormRequest
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
                Rule::unique('suppliers', 'code')->ignore($this->route('supplier')->id),
            ],
            'name' => ['sometimes', 'string', 'max:255'],
            'vat_number' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
