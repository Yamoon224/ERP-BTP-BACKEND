<?php

namespace App\Domains\Receiving\Http\Resources;

use App\Domains\Procurement\Http\Resources\SupplierResource;
use App\Models\DeliveryNote;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin DeliveryNote */
class DeliveryNoteResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'counts_as_received' => $this->status->countsAsReceived(),
            'received_at' => $this->received_at->toDateString(),
            'notes' => $this->notes,
            'purchase_order_id' => $this->purchase_order_id,
            'purchase_order' => $this->whenLoaded('purchaseOrder', fn (): array => [
                'id' => $this->purchaseOrder->id,
                'reference' => $this->purchaseOrder->reference,
                'status' => $this->purchaseOrder->status->value,
            ]),
            'supplier' => new SupplierResource($this->whenLoaded('supplier')),
            'lines' => DeliveryNoteLineResource::collection($this->whenLoaded('lines')),
            'lines_count' => $this->whenCounted('lines'),
            'received_by' => $this->whenLoaded('receiver', fn (): array => [
                'id' => $this->receiver->id,
                'name' => $this->receiver->name,
            ]),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
