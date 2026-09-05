<?php

namespace App\Domains\Procurement\Services;

use App\Domains\Procurement\Contracts\SupplierRepositoryContract;
use App\Models\Supplier;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class SupplierService
{
    public function __construct(private readonly SupplierRepositoryContract $suppliers) {}

    /** @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Supplier>
     */
    public function list(array $filters, int $perPage = 10): LengthAwarePaginator
    {
        return $this->suppliers->paginate($filters, $perPage);
    }

    public function find(int $id): Supplier
    {
        return $this->suppliers->findOrFail($id);
    }

    /** @param  array<string, mixed>  $data */
    public function create(array $data): Supplier
    {
        return $this->suppliers->create($data);
    }

    /** @param  array<string, mixed>  $data */
    public function update(Supplier $supplier, array $data): Supplier
    {
        return $this->suppliers->update($supplier, $data);
    }
}
