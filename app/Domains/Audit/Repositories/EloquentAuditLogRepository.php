<?php

namespace App\Domains\Audit\Repositories;

use App\Domains\Audit\Contracts\AuditLogRepositoryContract;
use App\Domains\Shared\Support\Sort;
use App\Models\ActivityLog;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Lecture seule du journal d'activite.
 *
 * Aucune methode d'ecriture, et ce n'est pas un oubli : les entrees sont
 * produites par les modeles eux-memes (trait `LogsActivity`). Exposer une
 * ecriture ici permettrait de fabriquer une trace, ce qui viderait le journal
 * de sa valeur — un audit auquel on peut ajouter des lignes a la main n'atteste
 * plus de rien.
 */
final class EloquentAuditLogRepository implements AuditLogRepositoryContract
{
    /** @var array<string, string> */
    private const SORTABLE = [
        'id' => 'id',
        'event' => 'event',
        'subject_type' => 'subject_type',
        'created_at' => 'created_at',
    ];

    /** @return LengthAwarePaginator<int, ActivityLog> */
    public function paginate(array $filters = [], int $perPage = 10): LengthAwarePaginator
    {
        return ActivityLog::query()
            ->with('causer')
            ->when($filters['event'] ?? null, fn ($query, $event) => $query->where('event', $event))
            ->when(
                $filters['subject_type'] ?? null,
                fn ($query, $type) => $query->where('subject_type', $type),
            )
            ->when(
                $filters['subject_id'] ?? null,
                fn ($query, $id) => $query->where('subject_id', $id),
            )
            ->when(
                $filters['causer_id'] ?? null,
                fn ($query, $id) => $query->where('causer_id', $id),
            )
            ->when($filters['from'] ?? null, fn ($query, $from) => $query->whereDate('created_at', '>=', $from))
            ->when($filters['to'] ?? null, fn ($query, $to) => $query->whereDate('created_at', '<=', $to))
            ->when($filters['search'] ?? null, fn ($query, $search) => $query->where(
                fn ($subQuery) => $subQuery
                    ->where('description', 'like', "%{$search}%")
                    ->orWhere('log_name', 'like', "%{$search}%")
                    ->orWhere('subject_type', 'like', "%{$search}%"),
            ))
            // Antichronologique par defaut : on ouvre un journal pour savoir ce
            // qui vient de se passer, pas ce qui s'est passe en premier.
            ->tap(fn ($query) => Sort::apply($query, $filters, self::SORTABLE, 'id', 'desc'))
            ->paginate($perPage)
            ->withQueryString();
    }

    public function findOrFail(string $id): ActivityLog
    {
        return ActivityLog::with('causer')->findOrFail($id);
    }

    /** @return array{subject_types: list<string>, events: list<string>} */
    public function facets(): array
    {
        return [
            'subject_types' => ActivityLog::query()
                ->whereNotNull('subject_type')
                ->distinct()
                ->orderBy('subject_type')
                ->pluck('subject_type')
                ->all(),
            'events' => ActivityLog::query()
                ->whereNotNull('event')
                ->distinct()
                ->orderBy('event')
                ->pluck('event')
                ->all(),
        ];
    }
}
