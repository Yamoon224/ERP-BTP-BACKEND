<?php

namespace App\Models;

use Database\Factories\DeliveryNoteLineFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $delivery_note_id
 * @property string $purchase_order_line_id
 * @property numeric-string $quantity_received
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read DeliveryNote $deliveryNote
 * @property-read PurchaseOrderLine $purchaseOrderLine
 */
class DeliveryNoteLine extends Model
{
    /** @use HasFactory<DeliveryNoteLineFactory> */
    use HasFactory, HasUuids;

    protected $fillable = ['delivery_note_id', 'purchase_order_line_id', 'quantity_received'];

    protected function casts(): array
    {
        return ['quantity_received' => 'decimal:3'];
    }

    /** @return BelongsTo<DeliveryNote, $this> */
    public function deliveryNote(): BelongsTo
    {
        return $this->belongsTo(DeliveryNote::class);
    }

    /** @return BelongsTo<PurchaseOrderLine, $this> */
    public function purchaseOrderLine(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderLine::class);
    }
}
