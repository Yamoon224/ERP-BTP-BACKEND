<?php

namespace App\Domains\Receiving\Repositories;

use App\Domains\Matching\Contracts\ReceivedQuantityReaderContract;
use App\Domains\Receiving\Enums\DeliveryNoteStatus;
use App\Models\DeliveryNoteLine;

/**
 * Quantités réceptionnées, agrégées par ligne de bon de commande.
 *
 * Seuls les BL au statut `accepted` sont comptés : un BL saisi mais non
 * contrôlé, ou une livraison refusée, ne doit jamais ouvrir un droit à
 * paiement. C'est la traduction technique du contrôle anti-livraison fictive.
 */
final class EloquentReceivedQuantityReader implements ReceivedQuantityReaderContract
{
    public function receivedQuantitiesForPurchaseOrder(string $purchaseOrderId): array
    {
        return DeliveryNoteLine::query()
            ->join('delivery_notes', 'delivery_notes.id', '=', 'delivery_note_lines.delivery_note_id')
            ->where('delivery_notes.purchase_order_id', $purchaseOrderId)
            ->where('delivery_notes.status', DeliveryNoteStatus::Accepted->value)
            ->groupBy('delivery_note_lines.purchase_order_line_id')
            ->selectRaw('delivery_note_lines.purchase_order_line_id, SUM(delivery_note_lines.quantity_received) as total')
            ->pluck('total', 'purchase_order_line_id')
            ->map(fn ($total): float => (float) $total)
            ->all();
    }
}
