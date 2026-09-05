<?php

namespace App\Domains\Procurement\Contracts;

use App\Models\Project;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface ProjectRepositoryContract
{
    /** @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Project>
     */
    public function paginate(array $filters = [], int $perPage = 10): LengthAwarePaginator;

    public function findOrFail(int $id): Project;

    /** @param  array<string, mixed>  $attributes */
    public function create(array $attributes): Project;

    /** @param  array<string, mixed>  $attributes */
    public function update(Project $project, array $attributes): Project;
}
