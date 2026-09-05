<?php

namespace App\Models;

use App\Domains\Procurement\Enums\PurchaseOrderStatus;
use App\Domains\Shared\Enums\Currency;
use Database\Factories\PurchaseOrderFactory;
use Illuminate\Database\Eloquent\Collection;
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
 * @property string $reference
 * @property string $supplier_id
 * @property string $project_id
 * @property Currency $currency
 * @property PurchaseOrderStatus $status
 * @property Carbon $ordered_at
 * @property string|null $notes
 * @property string|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Supplier $supplier
 * @property-read Project $project
 * @property-read User|null $creator
 * @property-read Collection<int, PurchaseOrderLine> $lines
 */
class PurchaseOrder extends Model
{
    /** @use HasFactory<PurchaseOrderFactory> */
    use HasFactory, HasUuids, LogsActivity;

    protected $fillable = [
        'reference',
        'supplier_id',
        'project_id',
        'currency',
        'status',
        'ordered_at',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'currency' => Currency::class,
            'status' => PurchaseOrderStatus::class,
            'ordered_at' => 'date',
        ];
    }

    /** Montant total commande, calcule depuis les lignes chargees. */
    public function totalAmount(): float
    {
        return round($this->lines->sum(
            fn (PurchaseOrderLine $line) => (float) $line->quantity_ordered * (float) $line->unit_price,
        ), 2);
    }

    /** @return BelongsTo<Supplier, $this> */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return HasMany<PurchaseOrderLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(PurchaseOrderLine::class);
    }

    /** @return HasMany<DeliveryNote, $this> */
    public function deliveryNotes(): HasMany
    {
        return $this->hasMany(DeliveryNote::class);
    }

    /** @return HasMany<Invoice, $this> */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['reference', 'supplier_id', 'project_id', 'status', 'currency'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
