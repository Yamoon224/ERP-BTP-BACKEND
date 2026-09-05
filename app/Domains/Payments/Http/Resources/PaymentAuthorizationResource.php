<?php

namespace App\Domains\Payments\Http\Resources;

use App\Models\PaymentAuthorization;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin PaymentAuthorization */
class PaymentAuthorizationResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'invoice_id' => $this->invoice_id,
            'match_run_id' => $this->match_run_id,
            'amount' => (float) $this->amount,
            'currency' => $this->currency->value,
            // Contre-valeur en devise de reference et taux applique : le
            // reglement part dans la devise de facture, le pilotage se
            // fait dans une devise unique.
            'base_amount' => (float) $this->base_amount,
            'base_currency' => $this->base_currency->value,
            'exchange_rate' => (float) $this->exchange_rate,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'authorized_at' => $this->authorized_at->toIso8601String(),
            // Le reglement est un fait qui s'ajoute a l'autorisation, pas un
            // statut qui la remplace : les deux informations coexistent.
            'is_settled' => $this->isSettled(),
            'settled_at' => $this->settled_at?->toIso8601String(),
            'payment_reference' => $this->payment_reference,
            'payment_method' => $this->payment_method,
            'settled_by' => $this->whenLoaded('settler', fn () => $this->settler === null ? null : [
                'id' => $this->settler->id,
                'name' => $this->settler->name,
            ]),
            'invoice' => $this->whenLoaded('invoice', fn (): array => [
                'id' => $this->invoice->id,
                'reference' => $this->invoice->reference,
                'status' => $this->invoice->status->value,
                'supplier' => $this->invoice->relationLoaded('supplier')
                    ? ['id' => $this->invoice->supplier->id, 'name' => $this->invoice->supplier->name]
                    : null,
            ]),
        ];
    }
}
