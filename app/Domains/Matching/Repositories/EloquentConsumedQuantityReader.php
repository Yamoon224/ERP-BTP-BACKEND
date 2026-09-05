<?php

namespace App\Domains\Matching\Repositories;

use App\Domains\Invoicing\Enums\InvoiceStatus;
use App\Domains\Matching\Contracts\ConsumedQuantityReaderContract;
use Illuminate\Support\Facades\DB;

/**
 * Quantités déjà rapprochées par les AUTRES factures du même bon de commande.
 *
 * Verrou anti-double paiement : sans cette lecture, deux factures portant la
 * même livraison seraient toutes deux jugées « couvertes par un BL » et
 * donneraient lieu à deux autorisations de paiement pour une seule livraison
 * réellement reçue.
 *
 * Seul le DERNIER rapprochement de chaque facture compte : les exécutions
 * précédentes sont de l'historique d'audit, pas une réservation de quantité.
 * Les factures annulées relâchent la quantité qu'elles retenaient.
 */
final class EloquentConsumedQuantityReader implements ConsumedQuantityReaderContract
{
    public function consumedQuantitiesForPurchaseOrder(string $purchaseOrderId, ?string $excludingInvoiceId = null): array
    {
        $competingInvoices = DB::table('invoices')
            ->select('id')
            ->where('purchase_order_id', $purchaseOrderId)
            ->where('status', '!=', InvoiceStatus::Cancelled->value)
            ->when($excludingInvoiceId !== null, fn ($query) => $query->where('id', '!=', $excludingInvoiceId));

        // Un seul rapprochement fait foi par facture : le plus récent.
        $authoritativeRuns = DB::table('match_runs')
            ->selectRaw('MAX(id) as id')
            ->whereIn('invoice_id', $competingInvoices)
            ->groupBy('invoice_id');

        return DB::table('match_line_results')
            ->joinSub($authoritativeRuns, 'latest_runs', 'latest_runs.id', '=', 'match_line_results.match_run_id')
            ->whereNotNull('match_line_results.purchase_order_line_id')
            ->groupBy('match_line_results.purchase_order_line_id')
            ->selectRaw('match_line_results.purchase_order_line_id, SUM(match_line_results.quantity_matched) as total')
            ->pluck('total', 'purchase_order_line_id')
            ->map(fn ($total): float => (float) $total)
            ->all();
    }
}
