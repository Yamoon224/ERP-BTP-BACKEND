<?php

namespace App\Domains\Procurement\Http\Resources;

use App\Models\PurchaseOrderLine;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin PurchaseOrderLine */
class PurchaseOrderLineResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'line_number' => $this->line_number,
            'item_code' => $this->item_code,
            'description' => $this->description,
            'unit' => $this->unit,
            'quantity_ordered' => (float) $this->quantity_ordered,
            'unit_price' => (float) $this->unit_price,
            'ordered_amount' => $this->orderedAmount(),
        ];
    }
}
