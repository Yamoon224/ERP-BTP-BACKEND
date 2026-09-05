<?php

namespace App\Domains\Invoicing\Http\Resources;

use App\Models\InvoiceLine;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin InvoiceLine */
class InvoiceLineResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'line_number' => $this->line_number,
            'description' => $this->description,
            'quantity' => (float) $this->quantity,
            'unit_price' => (float) $this->unit_price,
            'invoiced_amount' => $this->invoicedAmount(),
            'purchase_order_line_id' => $this->purchase_order_line_id,
            'purchase_order_line' => $this->whenLoaded('purchaseOrderLine', fn (): ?array => $this->purchaseOrderLine === null ? null : [
                'id' => $this->purchaseOrderLine->id,
                'line_number' => $this->purchaseOrderLine->line_number,
                'item_code' => $this->purchaseOrderLine->item_code,
                'unit' => $this->purchaseOrderLine->unit,
                'quantity_ordered' => (float) $this->purchaseOrderLine->quantity_ordered,
                'unit_price' => (float) $this->purchaseOrderLine->unit_price,
            ]),
        ];
    }
}
