<?php

namespace App\Domains\Matching\Contracts;

/**
 * Quantites deja rapprochees — donc deja couvertes par une autorisation de
 * paiement — par ligne de PO. Sans cette lecture, deux factures portant la
 * meme livraison seraient toutes deux payables : c'est la parade au double
 * paiement.
 */
interface ConsumedQuantityReaderContract
{
    /**
     * @param  string|null  $excludingInvoiceId  Facture en cours de rapprochement, exclue pour que
     *                                           rejouer un rapprochement ne se consomme pas lui-meme.
     * @return array<string, float> purchase_order_line_id => quantite deja rapprochee
     */
    public function consumedQuantitiesForPurchaseOrder(string $purchaseOrderId, ?string $excludingInvoiceId = null): array;
}
