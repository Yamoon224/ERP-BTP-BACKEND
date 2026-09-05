<?php

namespace App\Domains\Matching\Http\Resources;

use App\Models\MatchLineResult;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Detail chiffre d une ligne rapprochee. `evidence` est expose tel quel :
 * c est la preuve archivee sur laquelle la decision a ete prise, et un
 * controleur doit pouvoir la relire sans passer par la base.
 *
 * @mixin MatchLineResult
 */
class MatchLineResultResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'invoice_line_id' => $this->invoice_line_id,
            'purchase_order_line_id' => $this->purchase_order_line_id,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'quantity_invoiced' => (float) $this->quantity_invoiced,
            'quantity_matched' => (float) $this->quantity_matched,
            'quantity_unmatched' => (float) $this->quantity_unmatched,
            'unit_price_invoiced' => (float) $this->unit_price_invoiced,
            'unit_price_ordered' => $this->unit_price_ordered === null ? null : (float) $this->unit_price_ordered,
            'price_variance_ratio' => $this->price_variance_ratio === null ? null : (float) $this->price_variance_ratio,
            'matched_amount' => (float) $this->matched_amount,
            'evidence' => $this->evidence,
            'invoice_line' => $this->whenLoaded('invoiceLine', fn (): array => [
                'id' => $this->invoiceLine->id,
                'line_number' => $this->invoiceLine->line_number,
                'description' => $this->invoiceLine->description,
            ]),
        ];
    }
}
