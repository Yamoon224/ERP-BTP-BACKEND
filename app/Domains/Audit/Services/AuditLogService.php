<?php

namespace App\Domains\Audit\Services;

use App\Domains\Audit\Contracts\AuditLogRepositoryContract;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Spatie\Activitylog\Models\Activity;

/**
 * Consultation du journal d'activite.
 *
 * Le service n'expose aucune ecriture : la piste d'audit se constitue toute
 * seule au fil des ecritures metier. Lui ajouter une methode de creation
 * ouvrirait la porte a une trace fabriquee.
 */
final class AuditLogService
{
    public function __construct(private readonly AuditLogRepositoryContract $logs) {}

    /** @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Activity>
     */
    public function list(array $filters, int $perPage = 10): LengthAwarePaginator
    {
        return $this->logs->paginate($filters, $perPage);
    }

    public function find(int $id): Activity
    {
        return $this->logs->findOrFail($id);
    }

    /** @return array{subject_types: list<string>, events: list<string>} */
    public function facets(): array
    {
        return $this->logs->facets();
    }
}
