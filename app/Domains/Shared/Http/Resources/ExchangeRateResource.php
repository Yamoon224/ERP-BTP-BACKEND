<?php

namespace App\Domains\Shared\Http\Resources;

use App\Models\ExchangeRate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ExchangeRate */
class ExchangeRateResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'base_currency' => $this->base_currency->value,
            'quote_currency' => $this->quote_currency->value,
            // Multiplicateur : montant_en_quote = montant_en_base x rate.
            'rate' => (float) $this->rate,
            'source' => $this->source->value,
            'source_label' => $this->source->label(),
            // Une parite fixe est une donnee reglementaire : l'interface doit
            // pouvoir desactiver ses commandes plutot que d'attendre un 409.
            'is_editable' => $this->source->expires(),
            'effective_from' => $this->effective_from->toDateString(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
