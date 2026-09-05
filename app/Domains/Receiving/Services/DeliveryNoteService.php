<?php

namespace App\Domains\Receiving\Services;

use App\Domains\Matching\Services\InvoiceMatchingService;
use App\Domains\Procurement\Contracts\PurchaseOrderRepositoryContract;
use App\Domains\Procurement\Enums\PurchaseOrderStatus;
use App\Domains\Procurement\Exceptions\PurchaseOrderNotOpenException;
use App\Domains\Receiving\Contracts\DeliveryNoteRepositoryContract;
use App\Domains\Receiving\Enums\DeliveryNoteStatus;
use App\Domains\Receiving\Exceptions\DeliveryLineNotOnPurchaseOrderException;
use App\Domains\Receiving\Exceptions\DeliveryNoteNotEditableException;
use App\Models\DeliveryNote;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * Réception des marchandises. Un bon de livraison n'ouvre un droit à paiement
 * qu'une fois accepté : la saisie et le contrôle sont deux gestes distincts, et
 * c'est cette séparation qui empêche qu'une livraison fictive saisie au vol
 * devienne immédiatement payable.
 */
final class DeliveryNoteService
{
    public function __construct(
        private readonly DeliveryNoteRepositoryContract $deliveryNotes,
        private readonly PurchaseOrderRepositoryContract $purchaseOrders,
        private readonly InvoiceMatchingService $matchingService,
    ) {}

    /** @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, DeliveryNote>
     */
    public function list(array $filters, int $perPage = 10): LengthAwarePaginator
    {
        return $this->deliveryNotes->paginate($filters, $perPage);
    }

    public function find(string $id): DeliveryNote
    {
        return $this->deliveryNotes->findOrFail($id);
    }

    /**
     * @param  array{reference: string, purchase_order_id: string, received_at: string, notes?: string|null, lines: list<array{purchase_order_line_id: string, quantity_received: float}>}  $data
     *
     * @throws PurchaseOrderNotOpenException
     * @throws DeliveryLineNotOnPurchaseOrderException
     */
    public function record(array $data, User $receiver): DeliveryNote
    {
        $purchaseOrder = $this->purchaseOrders->findWithLinesOrFail($data['purchase_order_id']);
        $this->assertPurchaseOrderAcceptsDocuments($purchaseOrder);
        $this->assertLinesBelongToPurchaseOrder($purchaseOrder, array_column($data['lines'], 'purchase_order_line_id'));

        // Le fournisseur est repris du PO et jamais saisi : une livraison ne
        // peut pas, par construction, être rattachée à un autre fournisseur que
        // celui qui a été commandé.
        return $this->deliveryNotes->create([
            'reference' => $data['reference'],
            'purchase_order_id' => $purchaseOrder->id,
            'supplier_id' => $purchaseOrder->supplier_id,
            'status' => DeliveryNoteStatus::Draft,
            'received_at' => $data['received_at'],
            'received_by' => $receiver->id,
            'notes' => $data['notes'] ?? null,
        ], array_map(
            fn (array $line): array => [
                'purchase_order_line_id' => $line['purchase_order_line_id'],
                'quantity_received' => $line['quantity_received'],
            ],
            $data['lines'],
        ));
    }

    /**
     * Contrôle de réception. Accepter une livraison rend potentiellement
     * payables des factures déjà reçues : le rapprochement est donc relancé
     * dans la foulée, pour que l'état du système reflète immédiatement la
     * nouvelle réalité physique.
     *
     * @throws DeliveryNoteNotEditableException
     */
    public function review(DeliveryNote $deliveryNote, DeliveryNoteStatus $status, User $reviewer): DeliveryNote
    {
        if ($deliveryNote->status !== DeliveryNoteStatus::Draft) {
            throw DeliveryNoteNotEditableException::alreadyReviewed($deliveryNote->reference, $deliveryNote->status);
        }

        return DB::transaction(function () use ($deliveryNote, $status, $reviewer): DeliveryNote {
            $reviewed = $this->deliveryNotes->updateStatus($deliveryNote, $status);

            if ($status === DeliveryNoteStatus::Accepted) {
                $this->refreshPurchaseOrderReceptionStatus($reviewed->purchaseOrder);

                $this->matchingService->matchAllForPurchaseOrder(
                    $reviewed->purchase_order_id,
                    $reviewer,
                    InvoiceMatchingService::TRIGGER_DELIVERY_ACCEPTED,
                );
            }

            return $reviewed;
        });
    }

    /** @throws PurchaseOrderNotOpenException */
    private function assertPurchaseOrderAcceptsDocuments(PurchaseOrder $purchaseOrder): void
    {
        if (! $purchaseOrder->status->acceptsDocuments()) {
            throw PurchaseOrderNotOpenException::make($purchaseOrder->reference, $purchaseOrder->status);
        }
    }

    /**
     * @param  list<string>  $purchaseOrderLineIds
     *
     * @throws DeliveryLineNotOnPurchaseOrderException
     */
    private function assertLinesBelongToPurchaseOrder(PurchaseOrder $purchaseOrder, array $purchaseOrderLineIds): void
    {
        $validIds = $purchaseOrder->lines->pluck('id')->all();

        foreach ($purchaseOrderLineIds as $lineId) {
            if (! in_array($lineId, $validIds, true)) {
                throw DeliveryLineNotOnPurchaseOrderException::make($lineId, $purchaseOrder->reference);
            }
        }
    }

    /**
     * Avancement de la réception du PO, dérivé des quantités effectivement
     * reçues plutôt que saisi à la main.
     */
    private function refreshPurchaseOrderReceptionStatus(PurchaseOrder $purchaseOrder): void
    {
        if (in_array($purchaseOrder->status, [PurchaseOrderStatus::Closed, PurchaseOrderStatus::Cancelled], true)) {
            return;
        }

        $lines = $purchaseOrder->lines()->get();
        $received = DB::table('delivery_note_lines')
            ->join('delivery_notes', 'delivery_notes.id', '=', 'delivery_note_lines.delivery_note_id')
            ->where('delivery_notes.purchase_order_id', $purchaseOrder->id)
            ->where('delivery_notes.status', DeliveryNoteStatus::Accepted->value)
            ->groupBy('delivery_note_lines.purchase_order_line_id')
            ->selectRaw('delivery_note_lines.purchase_order_line_id as line_id, SUM(delivery_note_lines.quantity_received) as total')
            ->pluck('total', 'line_id');

        $fullyReceived = $lines->every(
            fn (PurchaseOrderLine $line): bool => (float) ($received[$line->id] ?? 0) >= (float) $line->quantity_ordered,
        );
        $anyReceived = $received->isNotEmpty();

        $status = match (true) {
            $fullyReceived => PurchaseOrderStatus::FullyReceived,
            $anyReceived => PurchaseOrderStatus::PartiallyReceived,
            default => $purchaseOrder->status,
        };

        if ($status !== $purchaseOrder->status) {
            $this->purchaseOrders->updateStatus($purchaseOrder, $status);
        }
    }
}
