<?php

namespace App\Models;

use Database\Factories\PurchaseOrderLineFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @property string $id
 * @property string $purchase_order_id
 * @property int $line_number
 * @property string $item_code
 * @property string $description
 * @property string $unit
 * @property numeric-string $quantity_ordered
 * @property numeric-string $unit_price
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read PurchaseOrder $purchaseOrder
 */
class PurchaseOrderLine extends Model
{
    /** @use HasFactory<PurchaseOrderLineFactory> */
    use HasFactory, HasUuids, LogsActivity;

    protected $fillable = [
        'purchase_order_id',
        'line_number',
        'item_code',
        'description',
        'unit',
        'quantity_ordered',
        'unit_price',
    ];

    protected function casts(): array
    {
        return [
            'quantity_ordered' => 'decimal:3',
            'unit_price' => 'decimal:4',
        ];
    }

    public function orderedAmount(): float
    {
        return round((float) $this->quantity_ordered * (float) $this->unit_price, 2);
    }

    /** @return BelongsTo<PurchaseOrder, $this> */
    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    /** @return HasMany<DeliveryNoteLine, $this> */
    public function deliveryNoteLines(): HasMany
    {
        return $this->hasMany(DeliveryNoteLine::class);
    }

    /** @return HasMany<InvoiceLine, $this> */
    public function invoiceLines(): HasMany
    {
        return $this->hasMany(InvoiceLine::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['quantity_ordered', 'unit_price', 'item_code'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
