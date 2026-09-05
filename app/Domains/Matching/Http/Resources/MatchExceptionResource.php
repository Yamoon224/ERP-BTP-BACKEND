<?php

namespace App\Domains\Matching\Http\Resources;

use App\Models\MatchException;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin MatchException */
class MatchExceptionResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'match_run_id' => $this->match_run_id,
            'invoice_id' => $this->invoice_id,
            'invoice_line_id' => $this->invoice_line_id,
            'type' => $this->type->value,
            'type_label' => $this->type->label(),
            'severity' => $this->severity->value,
            'is_overridable' => $this->type->isOverridable(),
            'message' => $this->message,
            'context' => $this->context,
            'review_status' => $this->review_status->value,
            'review_status_label' => $this->review_status->label(),
            'review_note' => $this->review_note,
            'reviewed_at' => $this->reviewed_at?->toIso8601String(),
            'reviewed_by' => $this->whenLoaded('reviewer', fn (): ?array => $this->reviewer === null ? null : [
                'id' => $this->reviewer->id,
                'name' => $this->reviewer->name,
            ]),
            'invoice' => $this->whenLoaded('invoice', fn (): array => [
                'id' => $this->invoice->id,
                'reference' => $this->invoice->reference,
                'status' => $this->invoice->status->value,
                'supplier' => $this->invoice->relationLoaded('supplier')
                    ? ['id' => $this->invoice->supplier->id, 'name' => $this->invoice->supplier->name]
                    : null,
            ]),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
