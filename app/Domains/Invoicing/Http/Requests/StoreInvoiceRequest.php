<?php

namespace App\Domains\Invoicing\Http\Requests;

use App\Domains\Shared\Enums\Currency;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Ni `supplier_id` ni `total_amount` ne sont acceptés en entrée : le
     * fournisseur est déduit du bon de commande et le total est recalculé
     * depuis les lignes. Laisser l'appelant fournir ces deux valeurs
     * ouvrirait précisément les fraudes que le contrôle à 3 voies existe pour
     * empêcher.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reference' => ['required', 'string', 'max:100'],
            'purchase_order_id' => ['required', 'uuid', Rule::exists('purchase_orders', 'id')],
            // Devise de facturation. Peut differer de celle du bon de
            // commande : le rapprochement convertira au taux du jour de
            // la facture, ou signalera l'absence de taux.
            'currency' => ['nullable', Rule::enum(Currency::class)],
            'invoice_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:invoice_date'],

            'lines' => ['required', 'array', 'min:1'],
            // Nullable : une ligne sans rattachement au PO est acceptée puis
            // signalée par le moteur, plutôt que rejetée à la saisie — sinon
            // la fraude reste invisible au lieu d'être tracée.
            'lines.*.purchase_order_line_id' => ['nullable', 'uuid', Rule::exists('purchase_order_lines', 'id')],
            'lines.*.description' => ['required', 'string', 'max:255'],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0'],
            'lines.*.unit_price' => ['required', 'numeric', 'gte:0'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'lines.required' => 'Une facture doit comporter au moins une ligne.',
            'lines.*.quantity.gt' => 'La quantité facturée doit être strictement positive.',
            'due_date.after_or_equal' => "L'échéance ne peut pas précéder la date de facture.",
        ];
    }
}
