<?php

namespace App\Domains\Receiving\Http\Resources;

use App\Models\DeliveryNoteLine;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin DeliveryNoteLine */
class DeliveryNoteLineResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'purchase_order_line_id' => $this->purchase_order_line_id,
            'quantity_received' => (float) $this->quantity_received,
            'purchase_order_line' => $this->whenLoaded('purchaseOrderLine', fn (): array => [
                'id' => $this->purchaseOrderLine->id,
                'line_number' => $this->purchaseOrderLine->line_number,
                'item_code' => $this->purchaseOrderLine->item_code,
                'description' => $this->purchaseOrderLine->description,
                'unit' => $this->purchaseOrderLine->unit,
                'quantity_ordered' => (float) $this->purchaseOrderLine->quantity_ordered,
            ]),
        ];
    }
}
