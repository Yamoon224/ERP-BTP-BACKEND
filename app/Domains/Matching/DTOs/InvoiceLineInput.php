<?php

namespace App\Domains\Matching\DTOs;

use App\Domains\Matching\Enums\DiscrepancyType;

final readonly class InvoiceLineInput
{
    /**
     * @param  float  $unitPrice  Prix unitaire tel que facturé, dans la devise de la facture.
     * @param  float|null  $unitPriceInComparisonCurrency  Le même prix converti dans la devise du
     *                                                     bon de commande, en pleine précision (non
     *                                                     arrondi : il sera multiplié par une
     *                                                     quantité). `null` si aucun taux n'est
     *                                                     disponible — le moteur le signalera.
     * @param  list<DiscrepancyType>  $approvedOverrides  Écarts déjà arbitrés favorablement sur
     *                                                    cette ligne, que le moteur ne doit pas
     *                                                    re-signaler.
     */
    public function __construct(
        public string $id,
        public int $lineNumber,
        public string $description,
        public float $quantity,
        public float $unitPrice,
        public ?PurchaseOrderLineSnapshot $purchaseOrderLine,
        public ?float $unitPriceInComparisonCurrency = null,
        public array $approvedOverrides = [],
    ) {}

    public function invoicedAmount(): float
    {
        return round($this->quantity * $this->unitPrice, 2);
    }

    /**
     * Prix unitaire à confronter au bon de commande. Sans conversion, c'est le
     * prix facturé lui-même.
     */
    public function comparableUnitPrice(): float
    {
        return $this->unitPriceInComparisonCurrency ?? $this->unitPrice;
    }

    public function hasApprovedOverrideFor(DiscrepancyType $type): bool
    {
        return in_array($type, $this->approvedOverrides, true);
    }
}
