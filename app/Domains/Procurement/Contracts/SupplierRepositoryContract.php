<?php

namespace App\Domains\Procurement\Contracts;

use App\Models\Supplier;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface SupplierRepositoryContract
{
    /** @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Supplier>
     */
    public function paginate(array $filters = [], int $perPage = 10): LengthAwarePaginator;

    public function findOrFail(int $id): Supplier;

    /** @param  array<string, mixed>  $attributes */
    public function create(array $attributes): Supplier;

    /** @param  array<string, mixed>  $attributes */
    public function update(Supplier $supplier, array $attributes): Supplier;

    public function delete(Supplier $supplier): void;

    /** Nombre de documents (commandes, factures) rattaches au fournisseur. */
    public function countDocuments(Supplier $supplier): int;
}
