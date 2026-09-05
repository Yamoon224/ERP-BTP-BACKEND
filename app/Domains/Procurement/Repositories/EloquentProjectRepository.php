<?php

namespace App\Domains\Procurement\Repositories;

use App\Domains\Procurement\Contracts\ProjectRepositoryContract;
use App\Domains\Shared\Support\Sort;
use App\Models\Project;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class EloquentProjectRepository implements ProjectRepositoryContract
{
    /** @var array<string, string> */
    private const SORTABLE = [
        'id' => 'id',
        'code' => 'code',
        'name' => 'name',
        'client_name' => 'client_name',
        'is_active' => 'is_active',
        'created_at' => 'created_at',
    ];

    /** @return LengthAwarePaginator<int, Project> */
    public function paginate(array $filters = [], int $perPage = 10): LengthAwarePaginator
    {
        return Project::query()
            ->when($filters['search'] ?? null, fn ($query, $search) => $query->where(
                fn ($subQuery) => $subQuery
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%")
                    ->orWhere('client_name', 'like', "%{$search}%"),
            ))
            ->when(
                array_key_exists('is_active', $filters) && $filters['is_active'] !== null,
                fn ($query) => $query->where('is_active', $filters['is_active']),
            )
            ->tap(fn ($query) => Sort::apply($query, $filters, self::SORTABLE, 'code', 'asc'))
            ->paginate($perPage)
            ->withQueryString();
    }

    public function findOrFail(int $id): Project
    {
        return Project::findOrFail($id);
    }

    public function create(array $attributes): Project
    {
        return Project::create($attributes);
    }

    public function update(Project $project, array $attributes): Project
    {
        $project->update($attributes);

        return $project->refresh();
    }
}
