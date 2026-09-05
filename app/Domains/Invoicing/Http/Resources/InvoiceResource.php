<?php

namespace App\Domains\Invoicing\Http\Resources;

use App\Domains\Matching\Http\Resources\MatchRunResource;
use App\Domains\Payments\Http\Resources\PaymentAuthorizationResource;
use App\Domains\Procurement\Http\Resources\SupplierResource;
use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Invoice */
class InvoiceResource extends JsonResource
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
            'invoice_date' => $this->invoice_date->toDateString(),
            'due_date' => $this->due_date?->toDateString(),
            'total_amount' => (float) $this->total_amount,
            'supplier' => new SupplierResource($this->whenLoaded('supplier')),
            'purchase_order_id' => $this->purchase_order_id,
            'purchase_order' => $this->whenLoaded('purchaseOrder', fn (): array => [
                'id' => $this->purchaseOrder->id,
                'reference' => $this->purchaseOrder->reference,
                'status' => $this->purchaseOrder->status->value,
                'currency' => $this->purchaseOrder->currency->value,
                'project' => $this->purchaseOrder->relationLoaded('project')
                    ? [
                        'id' => $this->purchaseOrder->project->id,
                        'code' => $this->purchaseOrder->project->code,
                        'name' => $this->purchaseOrder->project->name,
                    ]
                    : null,
            ]),
            'lines' => InvoiceLineResource::collection($this->whenLoaded('lines')),
            'lines_count' => $this->whenCounted('lines'),
            'open_exceptions_count' => $this->whenCounted('open_exceptions_count'),
            'latest_match_run' => $this->whenLoaded(
                'latestMatchRun',
                fn (): ?MatchRunResource => $this->latestMatchRun === null ? null : new MatchRunResource($this->latestMatchRun),
            ),
            'payment_authorization' => $this->whenLoaded(
                'paymentAuthorizations',
                fn (): ?PaymentAuthorizationResource => $this->paymentAuthorizations->first() === null
                    ? null
                    : new PaymentAuthorizationResource($this->paymentAuthorizations->first()),
            ),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
