<?php

namespace App\Domains\Receiving\Repositories;

use App\Domains\Receiving\Contracts\DeliveryNoteRepositoryContract;
use App\Domains\Receiving\Enums\DeliveryNoteStatus;
use App\Domains\Shared\Support\Sort;
use App\Models\DeliveryNote;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

final class EloquentDeliveryNoteRepository implements DeliveryNoteRepositoryContract
{
    /** @var array<string, string> */
    private const SORTABLE = [
        'id' => 'id',
        'reference' => 'reference',
        'status' => 'status',
        'received_at' => 'received_at',
        'supplier' => 'sort_supplier',
        'purchase_order' => 'sort_purchase_order',
        'lines' => 'lines_count',
    ];

    /** @return LengthAwarePaginator<int, DeliveryNote> */
    public function paginate(array $filters = [], int $perPage = 10): LengthAwarePaginator
    {
        return DeliveryNote::query()
            ->with(['supplier', 'purchaseOrder', 'receiver'])
            ->withCount('lines')
            ->when($filters['search'] ?? null, fn ($query, $search) => $query->where('reference', 'like', "%{$search}%"))
            ->when($filters['purchase_order_id'] ?? null, fn ($query, $id) => $query->where('purchase_order_id', $id))
            ->when($filters['supplier_id'] ?? null, fn ($query, $id) => $query->where('supplier_id', $id))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->addSelect(['sort_supplier' => Supplier::select('name')->whereColumn('suppliers.id', 'delivery_notes.supplier_id')])
            ->addSelect(['sort_purchase_order' => PurchaseOrder::select('reference')->whereColumn('purchase_orders.id', 'delivery_notes.purchase_order_id')])
            ->tap(fn ($query) => Sort::apply($query, $filters, self::SORTABLE, 'id', 'desc'))
            ->paginate($perPage)
            ->withQueryString();
    }

    public function findOrFail(string $id): DeliveryNote
    {
        return DeliveryNote::with([
            'supplier',
            'purchaseOrder',
            'receiver',
            'lines.purchaseOrderLine',
        ])->findOrFail($id);
    }

    public function create(array $attributes, array $lines): DeliveryNote
    {
        return DB::transaction(function () use ($attributes, $lines): DeliveryNote {
            $deliveryNote = DeliveryNote::create($attributes);
            $deliveryNote->lines()->createMany($lines);

            return $deliveryNote->load(['supplier', 'purchaseOrder', 'lines.purchaseOrderLine']);
        });
    }

    public function updateStatus(DeliveryNote $deliveryNote, DeliveryNoteStatus $status): DeliveryNote
    {
        $deliveryNote->update(['status' => $status]);

        return $deliveryNote->refresh()->load(['supplier', 'purchaseOrder', 'lines.purchaseOrderLine']);
    }
}
