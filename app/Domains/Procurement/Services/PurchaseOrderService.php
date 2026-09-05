<?php

namespace App\Domains\Procurement\Services;

use App\Domains\Procurement\Contracts\PurchaseOrderRepositoryContract;
use App\Domains\Procurement\Enums\PurchaseOrderStatus;
use App\Models\PurchaseOrder;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Cycle de vie des bons de commande. Le PO est le document de référence du
 * rapprochement : c'est lui qui fixe ce qui a été autorisé à l'achat, en
 * quantité comme en prix.
 */
final class PurchaseOrderService
{
    public function __construct(
        private readonly PurchaseOrderRepositoryContract $purchaseOrders,
    ) {}

    /** @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, PurchaseOrder>
     */
    public function list(array $filters, int $perPage = 10): LengthAwarePaginator
    {
        return $this->purchaseOrders->paginate($filters, $perPage);
    }

    public function find(int $id): PurchaseOrder
    {
        return $this->purchaseOrders->findWithLinesOrFail($id);
    }

    /**
     * @param  array{reference: string, supplier_id: int, project_id: int, currency?: string|null, ordered_at: string, notes?: string|null, lines: list<array<string, mixed>>}  $data
     */
    public function create(array $data, User $creator): PurchaseOrder
    {
        $lines = $this->numberLines($data['lines']);

        return $this->purchaseOrders->create([
            'reference' => $data['reference'],
            'supplier_id' => $data['supplier_id'],
            'project_id' => $data['project_id'],
            'currency' => strtoupper($data['currency'] ?? config('matching.default_currency')),
            'status' => PurchaseOrderStatus::Open,
            'ordered_at' => $data['ordered_at'],
            'notes' => $data['notes'] ?? null,
            'created_by' => $creator->id,
        ], $lines);
    }

    public function changeStatus(PurchaseOrder $purchaseOrder, PurchaseOrderStatus $status): PurchaseOrder
    {
        return $this->purchaseOrders->updateStatus($purchaseOrder, $status);
    }

    /**
     * Numérote les lignes dans l'ordre de saisie. Le numéro de ligne est la
     * référence stable citée dans les écarts et les messages de revue : il ne
     * peut pas dépendre de l'ordre de lecture en base.
     *
     * @param  list<array<string, mixed>>  $lines
     * @return list<array<string, mixed>>
     */
    private function numberLines(array $lines): array
    {
        return array_map(
            fn (array $line, int $index): array => [
                'line_number' => $index + 1,
                'item_code' => $line['item_code'],
                'description' => $line['description'],
                'unit' => $line['unit'],
                'quantity_ordered' => $line['quantity_ordered'],
                'unit_price' => $line['unit_price'],
            ],
            $lines,
            array_keys($lines),
        );
    }
}
