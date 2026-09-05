<?php

namespace App\Domains\Procurement\Services;

use App\Domains\Procurement\Contracts\ProjectRepositoryContract;
use App\Domains\Procurement\Exceptions\ProjectInUseException;
use App\Models\Project;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class ProjectService
{
    public function __construct(private readonly ProjectRepositoryContract $projects) {}

    /** @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Project>
     */
    public function list(array $filters, int $perPage = 10): LengthAwarePaginator
    {
        return $this->projects->paginate($filters, $perPage);
    }

    public function find(int $id): Project
    {
        return $this->projects->findOrFail($id);
    }

    /** @param  array<string, mixed>  $data */
    public function create(array $data): Project
    {
        return $this->projects->create($data);
    }

    /** @param  array<string, mixed>  $data */
    public function update(Project $project, array $data): Project
    {
        return $this->projects->update($project, $data);
    }

    /**
     * Supprime un chantier sur lequel rien n'a encore ete engage.
     *
     * @throws ProjectInUseException
     */
    public function delete(Project $project): void
    {
        $purchaseOrders = $this->projects->countPurchaseOrders($project);

        if ($purchaseOrders > 0) {
            throw ProjectInUseException::make($project->name, $purchaseOrders);
        }

        $this->projects->delete($project);
    }
}
