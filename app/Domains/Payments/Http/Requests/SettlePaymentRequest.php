<?php

namespace App\Domains\Payments\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Reglement d'une autorisation.
 *
 * Aucun montant n'est accepte : il est celui de l'autorisation, donc celui du
 * rapprochement. La reference du paiement est en revanche obligatoire — c'est
 * elle qui permettra de retrouver le virement dans le releve bancaire le jour
 * ou quelqu'un demandera « qu'avons-nous paye a ce fournisseur ? ».
 */
class SettlePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'payment_reference' => ['required', 'string', 'min:3', 'max:100'],
            'payment_method' => ['nullable', 'string', 'in:transfer,check,card,cash,direct_debit'],
            'settled_at' => ['nullable', 'date', 'before_or_equal:today'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'payment_reference.required' => 'La reference du reglement est obligatoire : sans elle, le paiement est introuvable en banque.',
            'settled_at.before_or_equal' => 'Un reglement ne peut pas etre date dans le futur.',
        ];
    }
}
