<?php

namespace App\Models;

use App\Domains\Matching\Enums\MatchStatus;
use Database\Factories\MatchLineResultFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $match_run_id
 * @property string $invoice_line_id
 * @property string|null $purchase_order_line_id
 * @property MatchStatus $status
 * @property numeric-string $quantity_invoiced
 * @property numeric-string $quantity_matched
 * @property numeric-string $quantity_unmatched
 * @property numeric-string $unit_price_invoiced
 * @property numeric-string|null $unit_price_ordered
 * @property numeric-string|null $price_variance_ratio
 * @property numeric-string $matched_amount
 * @property array<string, mixed> $evidence
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read MatchRun $matchRun
 * @property-read InvoiceLine $invoiceLine
 * @property-read PurchaseOrderLine|null $purchaseOrderLine
 */
class MatchLineResult extends Model
{
    /** @use HasFactory<MatchLineResultFactory> */
    use HasFactory, HasUuids;

    protected $fillable = [
        'match_run_id',
        'invoice_line_id',
        'purchase_order_line_id',
        'status',
        'quantity_invoiced',
        'quantity_matched',
        'quantity_unmatched',
        'unit_price_invoiced',
        'unit_price_ordered',
        'price_variance_ratio',
        'matched_amount',
        'evidence',
    ];

    protected function casts(): array
    {
        return [
            'status' => MatchStatus::class,
            'quantity_invoiced' => 'decimal:3',
            'quantity_matched' => 'decimal:3',
            'quantity_unmatched' => 'decimal:3',
            'unit_price_invoiced' => 'decimal:4',
            'unit_price_ordered' => 'decimal:4',
            'price_variance_ratio' => 'decimal:6',
            'matched_amount' => 'decimal:2',
            'evidence' => 'array',
        ];
    }

    /** @return BelongsTo<MatchRun, $this> */
    public function matchRun(): BelongsTo
    {
        return $this->belongsTo(MatchRun::class);
    }

    /** @return BelongsTo<InvoiceLine, $this> */
    public function invoiceLine(): BelongsTo
    {
        return $this->belongsTo(InvoiceLine::class);
    }

    /** @return BelongsTo<PurchaseOrderLine, $this> */
    public function purchaseOrderLine(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderLine::class);
    }
}
