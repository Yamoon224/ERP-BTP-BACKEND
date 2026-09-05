<?php

namespace App\Domains\Matching\Http\Resources;

use App\Models\MatchRun;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Réponse « piste d'audit » : qui a décidé, quand, avec quelle version du
 * moteur et quelles tolérances. Ces quatre informations sont toujours servies,
 * jamais conditionnées à un chargement de relation, parce qu'elles constituent
 * la réponse à la règle fonctionnelle n°5.
 *
 * @mixin MatchRun
 */
class MatchRunResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'invoice_id' => $this->invoice_id,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),

            'decided_by' => [
                'actor_type' => $this->actor_type->value,
                'actor_id' => $this->actor_id,
                'label' => $this->actorLabel(),
            ],
            'trigger' => $this->trigger,
            'engine_version' => $this->engine_version,
            'tolerance_snapshot' => $this->tolerance_snapshot,
            // Les taux appliques accompagnent la decision : un montant
            // converti sans son taux serait invérifiable.
            'exchange_rate_snapshot' => $this->exchange_rate_snapshot,
            'evaluated_at' => $this->evaluated_at->toIso8601String(),

            'currency' => $this->currency->value,
            'invoiced_amount' => (float) $this->invoiced_amount,
            'matched_amount' => (float) $this->matched_amount,
            'unmatched_amount' => (float) $this->unmatched_amount,
            // Contre-valeurs en devise de reference, pour l'agregation.
            'base_currency' => $this->base_currency->value,
            'base_matched_amount' => (float) $this->base_matched_amount,
            'base_unmatched_amount' => (float) $this->base_unmatched_amount,
            'exception_count' => $this->exception_count,

            // Contexte facture : dans le registre global, une execution sans
            // sa facture ne dit rien de ce qu'elle a decide.
            'invoice' => $this->whenLoaded('invoice', fn (): array => [
                'id' => $this->invoice->id,
                'reference' => $this->invoice->reference,
                'status' => $this->invoice->status->value,
                'currency' => $this->invoice->currency->value,
                'supplier' => $this->invoice->relationLoaded('supplier') && $this->invoice->supplier !== null
                    ? ['id' => $this->invoice->supplier->id, 'name' => $this->invoice->supplier->name]
                    : null,
            ]),

            'line_results' => MatchLineResultResource::collection($this->whenLoaded('lineResults')),
            'exceptions' => MatchExceptionResource::collection($this->whenLoaded('exceptions')),
            'payment_authorization' => $this->whenLoaded(
                'paymentAuthorization',
                fn (): ?array => $this->paymentAuthorization === null ? null : [
                    'id' => $this->paymentAuthorization->id,
                    'amount' => (float) $this->paymentAuthorization->amount,
                    'currency' => $this->paymentAuthorization->currency->value,
                    'status' => $this->paymentAuthorization->status->value,
                ],
            ),
            'line_results_count' => $this->whenCounted('lineResults'),
            'exceptions_count' => $this->whenCounted('exceptions'),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
