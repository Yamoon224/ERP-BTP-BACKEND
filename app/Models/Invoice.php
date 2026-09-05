<?php

namespace App\Models;

use App\Domains\Invoicing\Enums\InvoiceStatus;
use App\Domains\Shared\Enums\Currency;
use Database\Factories\InvoiceFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @property int $id
 * @property string $reference
 * @property int $supplier_id
 * @property int $purchase_order_id
 * @property Currency $currency
 * @property InvoiceStatus $status
 * @property Carbon $invoice_date
 * @property Carbon|null $due_date
 * @property numeric-string $total_amount
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Supplier $supplier
 * @property-read PurchaseOrder $purchaseOrder
 * @property-read User|null $creator
 * @property-read Collection<int, InvoiceLine> $lines
 * @property-read MatchRun|null $latestMatchRun
 * @property-read Collection<int, PaymentAuthorization> $paymentAuthorizations
 */
class Invoice extends Model
{
    /** @use HasFactory<InvoiceFactory> */
    use HasFactory, LogsActivity;

    protected $fillable = [
        'reference',
        'supplier_id',
        'purchase_order_id',
        'currency',
        'status',
        'invoice_date',
        'due_date',
        'total_amount',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'currency' => Currency::class,
            'status' => InvoiceStatus::class,
            'invoice_date' => 'date',
            'due_date' => 'date',
            'total_amount' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<Supplier, $this> */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /** @return BelongsTo<PurchaseOrder, $this> */
    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return HasMany<InvoiceLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(InvoiceLine::class);
    }

    /** @return HasMany<MatchRun, $this> */
    public function matchRuns(): HasMany
    {
        return $this->hasMany(MatchRun::class);
    }

    /**
     * Dernier rapprochement en date : c'est lui qui fait foi pour l'etat
     * courant de la facture, les precedents restent consultables pour l'audit.
     *
     * @return HasOne<MatchRun, $this>
     */
    public function latestMatchRun(): HasOne
    {
        return $this->hasOne(MatchRun::class)->latestOfMany();
    }

    /** @return HasMany<MatchException, $this> */
    public function matchExceptions(): HasMany
    {
        return $this->hasMany(MatchException::class);
    }

    /** @return HasMany<PaymentAuthorization, $this> */
    public function paymentAuthorizations(): HasMany
    {
        return $this->hasMany(PaymentAuthorization::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['reference', 'supplier_id', 'purchase_order_id', 'status', 'total_amount'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
