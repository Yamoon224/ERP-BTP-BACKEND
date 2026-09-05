<?php

namespace App\Domains\Shared\Http\Requests;

use App\Domains\Shared\Enums\Currency;
use App\Domains\Shared\Enums\ExchangeRateSource;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreExchangeRateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'base_currency' => ['required', Rule::in(Currency::values())],
            'quote_currency' => ['required', Rule::in(Currency::values())],
            'rate' => ['required', 'numeric', 'gt:0'],
            // La parite fixe n'est pas saisissable : elle est posee par le
            // referentiel initial, pas par un utilisateur.
            'source' => ['required', Rule::in([
                ExchangeRateSource::Manual->value,
                ExchangeRateSource::Provider->value,
            ])],
            'effective_from' => ['required', 'date'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->input('base_currency') === $this->input('quote_currency')) {
                $validator->errors()->add(
                    'quote_currency',
                    'Une devise ne se cote pas contre elle-meme : le taux vaut 1 par definition.',
                );
            }
        });
    }
}
