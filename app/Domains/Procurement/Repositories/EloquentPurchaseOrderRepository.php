<?php

namespace App\Domains\Procurement\Repositories;

use App\Domains\Procurement\Contracts\PurchaseOrderRepositoryContract;
use App\Domains\Procurement\Enums\PurchaseOrderStatus;
use App\Domains\Shared\Support\Sort;
use App\Models\Project;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\Supplier;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

final class EloquentPurchaseOrderRepository implements PurchaseOrderRepositoryContract
{
    /**
     * Colonnes offertes au tri par en-tete. Le fournisseur, le chantier et le
     * montant total ne sont pas des colonnes de cette table : ils sont tries
     * par sous-requete correlee, pour que l'ordre porte sur la donnee
     * reellement affichee et non sur un approximant.
     *
     * @var array<string, string>
     */
    private const SORTABLE = [
        'id' => 'id',
        'reference' => 'reference',
        'status' => 'status',
        'ordered_at' => 'ordered_at',
        'currency' => 'currency',
        'supplier' => 'sort_supplier',
        'project' => 'sort_project',
        'total' => 'computed_total_amount',
        'lines' => 'lines_count',
        'delivery_notes' => 'delivery_notes_count',
        'invoices' => 'invoices_count',
    ];

    /** @return LengthAwarePaginator<int, PurchaseOrder> */
    public function paginate(array $filters = [], int $perPage = 10): LengthAwarePaginator
    {
        return PurchaseOrder::query()
            ->with(['supplier', 'project'])
            ->withCount(['lines', 'deliveryNotes', 'invoices'])
            ->when($filters['search'] ?? null, fn ($query, $search) => $query->where('reference', 'like', "%{$search}%"))
            ->when($filters['supplier_id'] ?? null, fn ($query, $id) => $query->where('supplier_id', $id))
            ->when($filters['project_id'] ?? null, fn ($query, $id) => $query->where('project_id', $id))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->addSelect(['sort_supplier' => Supplier::select('name')->whereColumn('suppliers.id', 'purchase_orders.supplier_id')])
            ->addSelect(['sort_project' => Project::select('name')->whereColumn('projects.id', 'purchase_orders.project_id')])
            // Le total est calcule en base plutot que par chargement des
            // lignes : la liste en affiche 15 a 100, et charger toutes leurs
            // lignes pour n'en garder qu'une somme couterait une requete par
            // page pour rien.
            ->addSelect(['computed_total_amount' => PurchaseOrderLine::selectRaw('COALESCE(SUM(quantity_ordered * unit_price), 0)')
                ->whereColumn('purchase_order_lines.purchase_order_id', 'purchase_orders.id')])
            ->tap(fn ($query) => Sort::apply($query, $filters, self::SORTABLE, 'id', 'desc'))
            ->paginate($perPage)
            ->withQueryString();
    }

    public function findOrFail(int $id): PurchaseOrder
    {
        return PurchaseOrder::with(['supplier', 'project', 'creator'])->findOrFail($id);
    }

    public function findWithLinesOrFail(int $id): PurchaseOrder
    {
        return PurchaseOrder::with([
            'supplier',
            'project',
            'creator',
            'lines' => fn ($query) => $query->orderBy('line_number'),
        ])->findOrFail($id);
    }

    public function create(array $attributes, array $lines): PurchaseOrder
    {
        return DB::transaction(function () use ($attributes, $lines): PurchaseOrder {
            $purchaseOrder = PurchaseOrder::create($attributes);
            $purchaseOrder->lines()->createMany($lines);

            return $purchaseOrder->load(['supplier', 'project', 'lines']);
        });
    }

    public function updateStatus(PurchaseOrder $purchaseOrder, PurchaseOrderStatus $status): PurchaseOrder
    {
        $purchaseOrder->update(['status' => $status]);

        return $purchaseOrder->refresh();
    }
}
