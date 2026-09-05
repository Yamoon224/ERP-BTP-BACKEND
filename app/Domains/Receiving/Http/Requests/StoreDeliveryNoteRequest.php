<?php

namespace App\Domains\Receiving\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDeliveryNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'reference' => ['required', 'string', 'max:100'],
            'purchase_order_id' => ['required', 'uuid', Rule::exists('purchase_orders', 'id')],
            'received_at' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],

            'lines' => ['required', 'array', 'min:1'],
            'lines.*.purchase_order_line_id' => ['required', 'uuid', Rule::exists('purchase_order_lines', 'id')],
            'lines.*.quantity_received' => ['required', 'numeric', 'gt:0'],
        ];
    }

    /**
     * L'unicité (fournisseur, référence) est portée par un index en base
     * plutôt que vérifiée ici : le fournisseur n'est pas dans la requête (il
     * est déduit du PO), et seule la contrainte SQL est à l'abri d'une double
     * soumission concurrente.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'lines.required' => 'Un bon de livraison doit comporter au moins une ligne.',
            'lines.*.quantity_received.gt' => 'La quantité reçue doit être strictement positive.',
        ];
    }
}
