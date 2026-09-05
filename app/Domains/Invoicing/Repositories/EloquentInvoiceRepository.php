<?php

namespace App\Domains\Invoicing\Repositories;

use App\Domains\Invoicing\Contracts\InvoiceRepositoryContract;
use App\Domains\Invoicing\Enums\InvoiceStatus;
use App\Domains\Shared\Support\Sort;
use App\Models\Invoice;
use App\Models\Supplier;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

final class EloquentInvoiceRepository implements InvoiceRepositoryContract
{
    /** @var array<string, string> */
    private const SORTABLE = [
        'id' => 'id',
        'reference' => 'reference',
        'status' => 'status',
        'invoice_date' => 'invoice_date',
        'due_date' => 'due_date',
        'total' => 'total_amount',
        'currency' => 'currency',
        'supplier' => 'sort_supplier',
        'exceptions' => 'open_exceptions_count',
    ];

    /** @return LengthAwarePaginator<int, Invoice> */
    public function paginate(array $filters = [], int $perPage = 10): LengthAwarePaginator
    {
        return Invoice::query()
            ->with(['supplier', 'purchaseOrder', 'latestMatchRun'])
            ->withCount([
                'lines',
                'matchExceptions as open_exceptions_count' => fn ($query) => $query->open(),
            ])
            ->when($filters['search'] ?? null, fn ($query, $search) => $query->where('reference', 'like', "%{$search}%"))
            ->when($filters['supplier_id'] ?? null, fn ($query, $id) => $query->where('supplier_id', $id))
            ->when($filters['purchase_order_id'] ?? null, fn ($query, $id) => $query->where('purchase_order_id', $id))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['currency'] ?? null, fn ($query, $currency) => $query->where('currency', $currency))
            ->addSelect(['sort_supplier' => Supplier::select('name')->whereColumn('suppliers.id', 'invoices.supplier_id')])
            ->tap(fn ($query) => Sort::apply($query, $filters, self::SORTABLE, 'id', 'desc'))
            ->paginate($perPage)
            ->withQueryString();
    }

    public function findOrFail(string $id): Invoice
    {
        return Invoice::with([
            'supplier',
            'purchaseOrder.project',
            'creator',
            'lines.purchaseOrderLine',
            'latestMatchRun.actor',
            'latestMatchRun.lineResults',
            'latestMatchRun.exceptions.reviewer',
            'paymentAuthorizations' => fn ($query) => $query->active(),
        ])->findOrFail($id);
    }

    public function findForMatchingOrFail(string $id): Invoice
    {
        return Invoice::with([
            'purchaseOrder',
            'lines' => fn ($query) => $query->orderBy('line_number'),
            'lines.purchaseOrderLine',
        ])->findOrFail($id);
    }

    public function create(array $attributes, array $lines): Invoice
    {
        return DB::transaction(function () use ($attributes, $lines): Invoice {
            $invoice = Invoice::create($attributes);
            $invoice->lines()->createMany($lines);

            // Le total est dérivé des lignes, jamais fourni par l'appelant :
            // une facture dont l'en-tête annonce un montant différent de la
            // somme de ses lignes est le scénario de fraude le plus banal.
            $invoice->update(['total_amount' => $this->sumLines($lines)]);

            return $invoice->load(['supplier', 'purchaseOrder', 'lines.purchaseOrderLine']);
        });
    }

    public function updateStatus(Invoice $invoice, InvoiceStatus $status): Invoice
    {
        $invoice->update(['status' => $status]);

        return $invoice->refresh();
    }

    public function updateAttributes(Invoice $invoice, array $attributes): Invoice
    {
        $invoice->update($attributes);

        return $invoice->refresh();
    }

    public function existsForSupplierReference(string $supplierId, string $reference): bool
    {
        return Invoice::query()
            ->where('supplier_id', $supplierId)
            ->where('reference', $reference)
            ->exists();
    }

    /** @param  list<array<string, mixed>>  $lines */
    private function sumLines(array $lines): float
    {
        return round(array_sum(array_map(
            fn (array $line): float => round((float) $line['quantity'] * (float) $line['unit_price'], 2),
            $lines,
        )), 2);
    }
}
