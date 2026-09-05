<?php

namespace App\Domains\Procurement\Contracts;

use App\Domains\Procurement\Enums\PurchaseOrderStatus;
use App\Models\PurchaseOrder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface PurchaseOrderRepositoryContract
{
    /** @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, PurchaseOrder>
     */
    public function paginate(array $filters = [], int $perPage = 10): LengthAwarePaginator;

    public function findOrFail(int $id): PurchaseOrder;

    /** Charge le PO avec tout ce dont le rapprochement a besoin. */
    public function findWithLinesOrFail(int $id): PurchaseOrder;

    /**
     * @param  array<string, mixed>  $attributes
     * @param  list<array<string, mixed>>  $lines
     */
    public function create(array $attributes, array $lines): PurchaseOrder;

    public function updateStatus(PurchaseOrder $purchaseOrder, PurchaseOrderStatus $status): PurchaseOrder;
}
