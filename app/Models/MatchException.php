<?php

namespace App\Models;

use App\Domains\Matching\Enums\DiscrepancySeverity;
use App\Domains\Matching\Enums\DiscrepancyType;
use App\Domains\Matching\Enums\ReviewStatus;
use Database\Factories\MatchExceptionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Un ecart detecte, en attente d'arbitrage humain. L'arbitrage lui-meme est
 * trace ici (qui, quand, avec quel commentaire) plutot que dans un journal
 * separe : la decision et son motif restent attaches a l'ecart.
 *
 * @property string $id
 * @property string $match_run_id
 * @property string $invoice_id
 * @property string|null $invoice_line_id
 * @property string|null $match_line_result_id
 * @property DiscrepancyType $type
 * @property DiscrepancySeverity $severity
 * @property string $message
 * @property array<string, mixed> $context
 * @property ReviewStatus $review_status
 * @property string|null $reviewed_by
 * @property Carbon|null $reviewed_at
 * @property string|null $review_note
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read MatchRun $matchRun
 * @property-read Invoice $invoice
 * @property-read InvoiceLine|null $invoiceLine
 * @property-read MatchLineResult|null $lineResult
 * @property-read User|null $reviewer
 */
class MatchException extends Model
{
    /** @use HasFactory<MatchExceptionFactory> */
    use HasFactory, HasUuids, LogsActivity;

    protected $fillable = [
        'match_run_id',
        'invoice_id',
        'invoice_line_id',
        'match_line_result_id',
        'type',
        'severity',
        'message',
        'context',
        'review_status',
        'reviewed_by',
        'reviewed_at',
        'review_note',
    ];

    protected function casts(): array
    {
        return [
            'type' => DiscrepancyType::class,
            'severity' => DiscrepancySeverity::class,
            'review_status' => ReviewStatus::class,
            'context' => 'array',
            'reviewed_at' => 'datetime',
        ];
    }

    /** @param  Builder<MatchException>  $query */
    public function scopeOpen(Builder $query): void
    {
        $query->where('review_status', ReviewStatus::Open);
    }

    /** @return BelongsTo<MatchRun, $this> */
    public function matchRun(): BelongsTo
    {
        return $this->belongsTo(MatchRun::class);
    }

    /** @return BelongsTo<Invoice, $this> */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /** @return BelongsTo<InvoiceLine, $this> */
    public function invoiceLine(): BelongsTo
    {
        return $this->belongsTo(InvoiceLine::class);
    }

    /** @return BelongsTo<MatchLineResult, $this> */
    public function lineResult(): BelongsTo
    {
        return $this->belongsTo(MatchLineResult::class, 'match_line_result_id');
    }

    /** @return BelongsTo<User, $this> */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['review_status', 'reviewed_by', 'review_note'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
