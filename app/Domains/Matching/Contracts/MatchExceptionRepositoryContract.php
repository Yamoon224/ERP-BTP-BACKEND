<?php

namespace App\Domains\Matching\Contracts;

use App\Domains\Matching\Enums\ReviewStatus;
use App\Models\MatchException;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface MatchExceptionRepositoryContract
{
    /** @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, MatchException>
     */
    public function paginate(array $filters = [], int $perPage = 10): LengthAwarePaginator;

    public function findOrFail(string $id): MatchException;

    public function markReviewed(
        MatchException $exception,
        ReviewStatus $status,
        User $reviewer,
        ?string $note,
    ): MatchException;

    /** Nombre d'ecarts encore ouverts, pour le tableau de bord. */
    public function openCount(): int;
}
