<?php

namespace App\Domains\Audit\Contracts;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Spatie\Activitylog\Models\Activity;

interface AuditLogRepositoryContract
{
    /** @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Activity>
     */
    public function paginate(array $filters = [], int $perPage = 10): LengthAwarePaginator;

    public function findOrFail(int $id): Activity;

    /**
     * Valeurs distinctes des colonnes filtrables, pour alimenter les listes
     * deroulantes de l'ecran d'audit sans les coder en dur.
     *
     * @return array{subject_types: list<string>, events: list<string>}
     */
    public function facets(): array;
}
