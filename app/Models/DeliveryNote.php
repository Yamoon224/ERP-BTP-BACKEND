<?php

namespace App\Models;

use App\Domains\Receiving\Enums\DeliveryNoteStatus;
use Database\Factories\DeliveryNoteFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @property int $id
 * @property string $reference
 * @property int $purchase_order_id
 * @property int $supplier_id
 * @property DeliveryNoteStatus $status
 * @property Carbon $received_at
 * @property int|null $received_by
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read PurchaseOrder $purchaseOrder
 * @property-read Supplier $supplier
 * @property-read User|null $receiver
 * @property-read Collection<int, DeliveryNoteLine> $lines
 */
class DeliveryNote extends Model
{
    /** @use HasFactory<DeliveryNoteFactory> */
    use HasFactory, LogsActivity;

    protected $fillable = [
        'reference',
        'purchase_order_id',
        'supplier_id',
        'status',
        'received_at',
        'received_by',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'status' => DeliveryNoteStatus::class,
            'received_at' => 'date',
        ];
    }

    /**
     * Bons de livraison opposables au paiement. Utilise par le moteur de
     * rapprochement : seules les quantites d'un BL accepte sont payables.
     *
     * @param  Builder<DeliveryNote>  $query
     */
    public function scopeCountedAsReceived(Builder $query): void
    {
        $query->where('status', DeliveryNoteStatus::Accepted);
    }

    /** @return BelongsTo<PurchaseOrder, $this> */
    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    /** @return BelongsTo<Supplier, $this> */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /** @return BelongsTo<User, $this> */
    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    /** @return HasMany<DeliveryNoteLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(DeliveryNoteLine::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['reference', 'purchase_order_id', 'status', 'received_at'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
