<?php

namespace App\Domains\Matching\Contracts;

/**
 * Lecture seule des quantites receptionnees, exposee par le domaine Receiving.
 *
 * Le domaine Matching depend de cette interface etroite plutot que du depot
 * complet des bons de livraison (Interface Segregation) : il n'a aucun besoin
 * de creer ou modifier un BL.
 */
interface ReceivedQuantityReaderContract
{
    /**
     * Quantites cumulees receptionnees (BL acceptes uniquement) par ligne de PO.
     *
     * @return array<int, float> purchase_order_line_id => quantite recue
     */
    public function receivedQuantitiesForPurchaseOrder(int $purchaseOrderId): array;
}
