<?php

namespace App\Domains\Matching\Repositories;

use App\Domains\Matching\Contracts\MatchExceptionRepositoryContract;
use App\Domains\Matching\Enums\ReviewStatus;
use App\Domains\Shared\Support\Sort;
use App\Models\Invoice;
use App\Models\MatchException;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class EloquentMatchExceptionRepository implements MatchExceptionRepositoryContract
{
    /**
     * Rang metier des gravites. Trier « severity » alphabetiquement placerait
     * `critical` avant `low` et `medium` avant `high` : l'ordre affiche
     * n'aurait aucun rapport avec ce qu'il faut traiter en premier.
     */
    private const SEVERITY_RANK = "CASE severity WHEN 'low' THEN 1 WHEN 'medium' THEN 2 WHEN 'high' THEN 3 WHEN 'critical' THEN 4 ELSE 0 END";

    /** Meme raisonnement pour l'arbitrage : « a arbitrer » passe devant. */
    private const REVIEW_RANK = "CASE review_status WHEN 'open' THEN 0 ELSE 1 END";

    /** @var array<string, string|array{0: string, 1: string}> */
    private const SORTABLE = [
        'id' => 'id',
        'type' => 'type',
        'severity' => ['raw', self::SEVERITY_RANK],
        'review_status' => ['raw', self::REVIEW_RANK],
        'created_at' => 'created_at',
        'invoice' => 'sort_invoice',
    ];

    /** @return LengthAwarePaginator<int, MatchException> */
    public function paginate(array $filters = [], int $perPage = 10): LengthAwarePaginator
    {
        return MatchException::query()
            ->with(['invoice.supplier', 'invoiceLine', 'reviewer', 'matchRun'])
            ->when($filters['review_status'] ?? null, fn ($query, $status) => $query->where('review_status', $status))
            ->when($filters['type'] ?? null, fn ($query, $type) => $query->where('type', $type))
            ->when($filters['severity'] ?? null, fn ($query, $severity) => $query->where('severity', $severity))
            ->when($filters['invoice_id'] ?? null, fn ($query, $invoiceId) => $query->where('invoice_id', $invoiceId))
            ->when($filters['supplier_id'] ?? null, fn ($query, $supplierId) => $query->whereHas(
                'invoice',
                fn ($invoiceQuery) => $invoiceQuery->where('supplier_id', $supplierId),
            ))
            ->addSelect(['sort_invoice' => Invoice::select('reference')->whereColumn('invoices.id', 'match_exceptions.invoice_id')])
            // Sans tri demande, les écarts ouverts passent devant, puis les
            // plus récents : la file de revue doit présenter le travail à
            // faire avant l'historique. Un tri explicite prime sur cette
            // preference, sinon la colonne cliquee ne ferait rien.
            ->when(
                ! isset($filters['sort']),
                fn ($query) => $query->orderByRaw('CASE WHEN review_status = ? THEN 0 ELSE 1 END', [ReviewStatus::Open->value]),
            )
            ->tap(fn ($query) => Sort::apply($query, $filters, self::SORTABLE, 'id', 'desc'))
            ->paginate($perPage)
            ->withQueryString();
    }

    public function findOrFail(int $id): MatchException
    {
        return MatchException::with(['invoice.supplier', 'invoiceLine', 'lineResult', 'reviewer', 'matchRun'])
            ->findOrFail($id);
    }

    public function markReviewed(
        MatchException $exception,
        ReviewStatus $status,
        User $reviewer,
        ?string $note,
    ): MatchException {
        $exception->update([
            'review_status' => $status,
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
            'review_note' => $note,
        ]);

        return $exception->fresh(['reviewer', 'invoice']);
    }

    public function openCount(): int
    {
        return MatchException::query()->open()->count();
    }
}
