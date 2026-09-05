<?php

namespace App\Models;

use App\Domains\Payments\Enums\PaymentAuthorizationStatus;
use App\Domains\Shared\Enums\Currency;
use Database\Factories\PaymentAuthorizationFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @property string $id
 * @property string $invoice_id
 * @property string $match_run_id
 * @property Currency $currency
 * @property Currency $base_currency
 * @property numeric-string $base_amount
 * @property numeric-string $exchange_rate
 * @property numeric-string $amount
 * @property PaymentAuthorizationStatus $status
 * @property Carbon $authorized_at
 * @property Carbon|null $settled_at
 * @property string|null $payment_reference
 * @property string|null $payment_method
 * @property string|null $settled_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Invoice $invoice
 * @property-read MatchRun $matchRun
 * @property-read User|null $settler
 */
class PaymentAuthorization extends Model
{
    /** @use HasFactory<PaymentAuthorizationFactory> */
    use HasFactory, HasUuids, LogsActivity;

    protected $fillable = [
        'invoice_id',
        'match_run_id',
        'currency',
        'amount',
        'base_currency',
        'base_amount',
        'exchange_rate',
        'status',
        'authorized_at',
        'settled_at',
        'payment_reference',
        'payment_method',
        'settled_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => PaymentAuthorizationStatus::class,
            'currency' => Currency::class,
            'base_currency' => Currency::class,
            'amount' => 'decimal:2',
            'base_amount' => 'decimal:2',
            'exchange_rate' => 'decimal:10',
            'authorized_at' => 'datetime',
            'settled_at' => 'datetime',
        ];
    }

    /** @param  Builder<PaymentAuthorization>  $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('status', PaymentAuthorizationStatus::Active);
    }

    /**
     * Autorisations encore modifiables par le moteur : reglees, elles ne le
     * sont plus. Remplacer ou revoquer une autorisation deja payee reecrirait
     * l'histoire d'un virement parti.
     *
     * @param  Builder<PaymentAuthorization>  $query
     */
    public function scopeUnsettled(Builder $query): void
    {
        $query->whereNull('settled_at');
    }

    public function isSettled(): bool
    {
        return $this->settled_at !== null;
    }

    /** @return BelongsTo<Invoice, $this> */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /** @return BelongsTo<MatchRun, $this> */
    public function matchRun(): BelongsTo
    {
        return $this->belongsTo(MatchRun::class);
    }

    /** @return BelongsTo<User, $this> */
    public function settler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'settled_by');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['amount', 'status', 'settled_at', 'payment_reference'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
