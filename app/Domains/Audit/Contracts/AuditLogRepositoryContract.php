<?php

namespace App\Domains\Audit\Contracts;

use App\Models\ActivityLog;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface AuditLogRepositoryContract
{
    /** @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, ActivityLog>
     */
    public function paginate(array $filters = [], int $perPage = 10): LengthAwarePaginator;

    public function findOrFail(string $id): ActivityLog;

    /**
     * Valeurs distinctes des colonnes filtrables, pour alimenter les listes
     * deroulantes de l'ecran d'audit sans les coder en dur.
     *
     * @return array{subject_types: list<string>, events: list<string>}
     */
    public function facets(): array;
}
