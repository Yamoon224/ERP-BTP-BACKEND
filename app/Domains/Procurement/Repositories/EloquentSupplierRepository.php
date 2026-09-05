<?php

namespace App\Domains\Procurement\Repositories;

use App\Domains\Procurement\Contracts\SupplierRepositoryContract;
use App\Domains\Shared\Support\Sort;
use App\Models\Supplier;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class EloquentSupplierRepository implements SupplierRepositoryContract
{
    /** @var array<string, string> */
    private const SORTABLE = [
        'id' => 'id',
        'code' => 'code',
        'name' => 'name',
        'email' => 'email',
        'is_active' => 'is_active',
        'created_at' => 'created_at',
    ];

    /** @return LengthAwarePaginator<int, Supplier> */
    public function paginate(array $filters = [], int $perPage = 10): LengthAwarePaginator
    {
        return Supplier::query()
            ->when($filters['search'] ?? null, fn ($query, $search) => $query->where(
                fn ($subQuery) => $subQuery
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%"),
            ))
            ->when(
                array_key_exists('is_active', $filters) && $filters['is_active'] !== null,
                fn ($query) => $query->where('is_active', $filters['is_active']),
            )
            ->tap(fn ($query) => Sort::apply($query, $filters, self::SORTABLE, 'name', 'asc'))
            ->paginate($perPage)
            ->withQueryString();
    }

    public function findOrFail(string $id): Supplier
    {
        return Supplier::findOrFail($id);
    }

    public function create(array $attributes): Supplier
    {
        return Supplier::create($attributes);
    }

    public function update(Supplier $supplier, array $attributes): Supplier
    {
        $supplier->update($attributes);

        return $supplier->refresh();
    }

    public function delete(Supplier $supplier): void
    {
        $supplier->delete();
    }

    public function countDocuments(Supplier $supplier): int
    {
        return $supplier->purchaseOrders()->count() + $supplier->invoices()->count();
    }
}
