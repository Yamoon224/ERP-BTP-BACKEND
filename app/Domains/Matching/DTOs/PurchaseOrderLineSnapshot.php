<?php

namespace App\Domains\Matching\DTOs;

/**
 * Ligne de bon de commande telle qu'elle est vue par le moteur, augmentee des
 * quantites deja recues et deja rapprochees. Le moteur ne connait pas Eloquent :
 * il raisonne uniquement sur ces valeurs, ce qui le rend testable sans base.
 */
final readonly class PurchaseOrderLineSnapshot
{
    public function __construct(
        public int $id,
        public int $lineNumber,
        public string $itemCode,
        public float $quantityOrdered,
        public float $unitPrice,
        /** Quantite cumulee sur les BL acceptes de ce PO. */
        public float $quantityReceived,
        /**
         * Quantite deja rapprochee (donc deja payable) par d'AUTRES factures.
         * C'est le verrou anti-double paiement : une meme quantite livree ne
         * peut pas etre payee deux fois via deux factures.
         */
        public float $quantityAlreadyMatched,
    ) {}

    /**
     * Quantite encore payable sur cette ligne : bornee a la fois par ce qui a
     * ete commande ET par ce qui a ete recu (regle fonctionnelle n4), moins ce
     * qui a deja ete rapproche ailleurs.
     */
    public function quantityAvailableForMatching(): float
    {
        return max(min($this->quantityOrdered, $this->quantityReceived) - $this->quantityAlreadyMatched, 0.0);
    }

    /** Quantite encore commandable/facturable au titre du PO. */
    public function quantityRemainingOnOrder(): float
    {
        return max($this->quantityOrdered - $this->quantityAlreadyMatched, 0.0);
    }
}
