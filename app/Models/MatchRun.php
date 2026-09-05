<?php

namespace App\Models;

use App\Domains\Matching\Enums\ActorType;
use App\Domains\Matching\Enums\MatchStatus;
use App\Domains\Shared\Enums\Currency;
use Database\Factories\MatchRunFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * Une execution du moteur de rapprochement sur une facture. Immuable une fois
 * ecrite : rejouer le rapprochement cree une nouvelle execution.
 *
 * @property int $id
 * @property int $invoice_id
 * @property ActorType $actor_type
 * @property int|null $actor_id
 * @property string $trigger
 * @property string $engine_version
 * @property array<string, float> $tolerance_snapshot
 * @property MatchStatus $status
 * @property Currency $currency
 * @property Currency $base_currency
 * @property numeric-string $base_matched_amount
 * @property numeric-string $base_unmatched_amount
 * @property array<string, mixed>|null $exchange_rate_snapshot
 * @property numeric-string $invoiced_amount
 * @property numeric-string $matched_amount
 * @property numeric-string $unmatched_amount
 * @property int $exception_count
 * @property Carbon $evaluated_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Invoice $invoice
 * @property-read User|null $actor
 * @property-read Collection<int, MatchLineResult> $lineResults
 * @property-read Collection<int, MatchException> $exceptions
 * @property-read PaymentAuthorization|null $paymentAuthorization
 */
class MatchRun extends Model
{
    /** @use HasFactory<MatchRunFactory> */
    use HasFactory;

    protected $fillable = [
        'invoice_id',
        'actor_type',
        'actor_id',
        'trigger',
        'engine_version',
        'tolerance_snapshot',
        'status',
        'currency',
        'invoiced_amount',
        'matched_amount',
        'unmatched_amount',
        'base_currency',
        'base_matched_amount',
        'base_unmatched_amount',
        'exchange_rate_snapshot',
        'exception_count',
        'evaluated_at',
    ];

    protected function casts(): array
    {
        return [
            'actor_type' => ActorType::class,
            'status' => MatchStatus::class,
            'currency' => Currency::class,
            'base_currency' => Currency::class,
            'tolerance_snapshot' => 'array',
            'exchange_rate_snapshot' => 'array',
            'base_matched_amount' => 'decimal:2',
            'base_unmatched_amount' => 'decimal:2',
            'invoiced_amount' => 'decimal:2',
            'matched_amount' => 'decimal:2',
            'unmatched_amount' => 'decimal:2',
            'evaluated_at' => 'datetime',
        ];
    }

    /** Libelle lisible de l'auteur de la decision (regle fonctionnelle n5). */
    public function actorLabel(): string
    {
        if ($this->actor_type === ActorType::System) {
            return "Moteur de rapprochement v{$this->engine_version}";
        }

        return $this->actor->name ?? 'Utilisateur supprime';
    }

    /** @return BelongsTo<Invoice, $this> */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    /** @return HasMany<MatchLineResult, $this> */
    public function lineResults(): HasMany
    {
        return $this->hasMany(MatchLineResult::class);
    }

    /** @return HasMany<MatchException, $this> */
    public function exceptions(): HasMany
    {
        return $this->hasMany(MatchException::class);
    }

    /** @return HasOne<PaymentAuthorization, $this> */
    public function paymentAuthorization(): HasOne
    {
        return $this->hasOne(PaymentAuthorization::class);
    }
}
