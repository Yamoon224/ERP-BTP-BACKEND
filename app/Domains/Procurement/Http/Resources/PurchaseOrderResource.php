<?php

namespace App\Domains\Procurement\Http\Resources;

use App\Models\PurchaseOrder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin PurchaseOrder */
class PurchaseOrderResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'currency' => $this->currency->value,
            'ordered_at' => $this->ordered_at->toDateString(),
            'notes' => $this->notes,
            'supplier' => new SupplierResource($this->whenLoaded('supplier')),
            'project' => new ProjectResource($this->whenLoaded('project')),
            'lines' => PurchaseOrderLineResource::collection($this->whenLoaded('lines')),
            // En detail, le total vient des lignes chargees ; en liste, de la
            // somme calculee par le depot. Dans les deux cas il est present :
            // une colonne « Montant » vide sur un ecran d'engagement d'achat
            // n'aurait aucun interet.
            // `computed_total_amount` est une colonne ajoutee par le depot au
            // moment de la liste, pas un attribut du modele : elle se lit donc
            // par `getAttribute`, sinon l'analyse statique la cherche — a
            // raison — parmi les proprietes declarees.
            'total_amount' => $this->relationLoaded('lines')
                ? $this->totalAmount()
                : $this->when(
                    $this->resource->getAttribute('computed_total_amount') !== null,
                    fn (): float => (float) $this->resource->getAttribute('computed_total_amount'),
                ),
            'lines_count' => $this->whenCounted('lines'),
            'delivery_notes_count' => $this->whenCounted('deliveryNotes'),
            'invoices_count' => $this->whenCounted('invoices'),
            'created_by' => $this->whenLoaded('creator', fn (): array => [
                'id' => $this->creator->id,
                'name' => $this->creator->name,
            ]),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
