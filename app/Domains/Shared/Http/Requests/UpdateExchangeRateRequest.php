<?php

namespace App\Domains\Shared\Http\Requests;

use App\Domains\Shared\Enums\ExchangeRateSource;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Correction d'une cotation.
 *
 * Ni la devise de base ni la devise cotee ne sont modifiables : changer la
 * paire d'une ligne existante reecrirait l'historique d'une *autre* paire.
 * Pour coter une nouvelle paire, on cree une ligne.
 */
class UpdateExchangeRateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'rate' => ['sometimes', 'numeric', 'gt:0'],
            'source' => ['sometimes', Rule::in([
                ExchangeRateSource::Manual->value,
                ExchangeRateSource::Provider->value,
            ])],
            'effective_from' => ['sometimes', 'date'],
        ];
    }
}
