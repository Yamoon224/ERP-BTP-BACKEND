<?php

namespace App\Domains\Matching\DTOs;

use App\Domains\Matching\Enums\MatchStatus;

/**
 * Verdict du moteur pour une ligne de facture, avec le detail chiffre qui l'a
 * produit (`evidence`). Cette preuve est persistee telle quelle : elle permet
 * d'expliquer la decision des mois plus tard, meme si le PO a evolue depuis.
 */
final readonly class LineOutcome
{
    /**
     * @param  list<Discrepancy>  $discrepancies
     * @param  array<string, mixed>  $evidence
     */
    public function __construct(
        public string $invoiceLineId,
        public ?string $purchaseOrderLineId,
        public MatchStatus $status,
        public float $quantityInvoiced,
        public float $quantityMatched,
        public float $quantityUnmatched,
        public float $unitPriceInvoiced,
        public ?float $unitPriceOrdered,
        public ?float $priceVarianceRatio,
        public float $matchedAmount,
        public array $evidence,
        public array $discrepancies = [],
    ) {}
}
