<?php

namespace App\Domains\Procurement\Http\Requests;

use App\Domains\Shared\Enums\Currency;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePurchaseOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'reference' => ['required', 'string', 'max:100', Rule::unique('purchase_orders', 'reference')],
            'supplier_id' => ['required', 'uuid', Rule::exists('suppliers', 'id')],
            'project_id' => ['required', 'uuid', Rule::exists('projects', 'id')],
            // La devise du bon de commande devient la reference de
            // comparaison des prix : elle doit etre une devise supportee,
            // pas une chaine de trois caracteres quelconque.
            'currency' => ['nullable', Rule::enum(Currency::class)],
            'ordered_at' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],

            'lines' => ['required', 'array', 'min:1'],
            'lines.*.item_code' => ['required', 'string', 'max:100'],
            'lines.*.description' => ['required', 'string', 'max:255'],
            'lines.*.unit' => ['required', 'string', 'max:16'],
            // Quantité et prix strictement positifs : une ligne à zéro n'a pas
            // de sens à l'achat et fausserait les ratios d'écart au
            // rapprochement (division par un prix nul).
            'lines.*.quantity_ordered' => ['required', 'numeric', 'gt:0'],
            'lines.*.unit_price' => ['required', 'numeric', 'gt:0'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'lines.required' => 'Un bon de commande doit comporter au moins une ligne.',
            'lines.*.quantity_ordered.gt' => 'La quantité commandée doit être strictement positive.',
            'lines.*.unit_price.gt' => 'Le prix unitaire doit être strictement positif.',
        ];
    }
}
