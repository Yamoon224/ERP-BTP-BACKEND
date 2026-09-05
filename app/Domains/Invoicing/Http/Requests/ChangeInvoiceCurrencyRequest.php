<?php

namespace App\Domains\Invoicing\Http\Requests;

use App\Domains\Shared\Enums\Currency;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ChangeInvoiceCurrencyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'currency' => ['required', 'string', Rule::in(Currency::values())],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'currency.in' => 'Devise inconnue du circuit achats.',
        ];
    }
}
