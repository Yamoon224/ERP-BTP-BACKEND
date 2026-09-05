<?php

namespace App\Domains\Matching\Services;

use App\Domains\Matching\Contracts\ApprovedOverrideReaderContract;
use App\Domains\Matching\Contracts\ConsumedQuantityReaderContract;
use App\Domains\Matching\Contracts\ReceivedQuantityReaderContract;
use App\Domains\Matching\DTOs\InvoiceLineInput;
use App\Domains\Matching\DTOs\InvoiceMatchInput;
use App\Domains\Matching\DTOs\PurchaseOrderLineSnapshot;
use App\Domains\Shared\DTOs\ExchangeRate;
use App\Domains\Shared\Enums\Currency;
use App\Domains\Shared\Services\CurrencyConverter;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\PurchaseOrderLine;

/**
 * Traduit l'état de la base en photographie immuable pour le moteur.
 *
 * Cette séparation est délibérée : elle concentre ici tout ce qui touche à
 * Eloquent, aux agrégats SQL **et à la résolution des taux de change**, et
 * laisse le moteur ne raisonner que sur des valeurs. C'est aussi ce qui
 * garantit que les trois voies sont lues en une seule fois, de façon cohérente,
 * plutôt que par requêtes dispersées au fil du calcul.
 *
 * Les taux sont résolus **à la date de la facture**, et non à celle du
 * rapprochement : c'est la date à laquelle la créance est née qui fait foi.
 * Rejouer un rapprochement des mois plus tard redonne donc le même montant.
 */
final class MatchInputAssembler
{
    public function __construct(
        private readonly ReceivedQuantityReaderContract $receivedQuantities,
        private readonly ConsumedQuantityReaderContract $consumedQuantities,
        private readonly ApprovedOverrideReaderContract $approvedOverrides,
        private readonly CurrencyConverter $currencyConverter,
        private readonly Currency $baseCurrency,
    ) {}

    public function assemble(Invoice $invoice): InvoiceMatchInput
    {
        $invoice->loadMissing(['lines.purchaseOrderLine', 'purchaseOrder']);
        $purchaseOrder = $invoice->purchaseOrder;

        $invoiceCurrency = $invoice->currency;
        $comparisonCurrency = $purchaseOrder->currency;
        $on = $invoice->invoice_date->toDateString();

        $received = $this->receivedQuantities->receivedQuantitiesForPurchaseOrder($purchaseOrder->id);
        $consumed = $this->consumedQuantities->consumedQuantitiesForPurchaseOrder($purchaseOrder->id, $invoice->id);
        $overrides = $this->approvedOverrides->approvedOverridesForInvoice($invoice->id);

        // Un taux introuvable n'est pas une erreur technique ici : c'est un
        // écart métier que le moteur doit pouvoir signaler. On le laisse donc
        // à null plutôt que de laisser remonter l'exception.
        $invoiceToComparison = $this->rateOrNull($invoiceCurrency, $comparisonCurrency, $on);
        $invoiceToBase = $this->rateOrNull($invoiceCurrency, $this->baseCurrency, $on);
        $baseToComparison = $this->rateOrNull($this->baseCurrency, $comparisonCurrency, $on);

        $lines = $invoice->lines
            ->sortBy('line_number')
            ->map(fn (InvoiceLine $line): InvoiceLineInput => new InvoiceLineInput(
                id: $line->id,
                lineNumber: $line->line_number,
                description: $line->description,
                quantity: (float) $line->quantity,
                unitPrice: (float) $line->unit_price,
                purchaseOrderLine: $this->snapshotPurchaseOrderLine($line->purchaseOrderLine, $received, $consumed),
                // Prix converti en pleine précision : il sera multiplié par une
                // quantité, arrondir ici décalerait le total de la ligne.
                unitPriceInComparisonCurrency: $invoiceToComparison?->convert((float) $line->unit_price),
                approvedOverrides: $overrides[$line->id] ?? [],
            ))
            ->values()
            ->all();

        return new InvoiceMatchInput(
            invoiceId: $invoice->id,
            invoiceReference: $invoice->reference,
            invoiceSupplierId: $invoice->supplier_id,
            invoiceCurrency: $invoiceCurrency,
            purchaseOrderId: $purchaseOrder->id,
            purchaseOrderReference: $purchaseOrder->reference,
            purchaseOrderSupplierId: $purchaseOrder->supplier_id,
            purchaseOrderCurrency: $comparisonCurrency,
            purchaseOrderAcceptsDocuments: $purchaseOrder->status->acceptsDocuments(),
            lines: $lines,
            invoiceToComparisonRate: $invoiceToComparison,
            invoiceToBaseRate: $invoiceToBase,
            baseToComparisonRate: $baseToComparison,
            baseCurrency: $this->baseCurrency,
        );
    }

    private function rateOrNull(Currency $from, Currency $to, string $on): ?ExchangeRate
    {
        if (! $this->currencyConverter->hasRateFor($from, $to, $on)) {
            return null;
        }

        return $this->currencyConverter->rateFor($from, $to, $on);
    }

    /**
     * @param  array<string, float>  $received
     * @param  array<string, float>  $consumed
     */
    private function snapshotPurchaseOrderLine(
        ?PurchaseOrderLine $line,
        array $received,
        array $consumed,
    ): ?PurchaseOrderLineSnapshot {
        if ($line === null) {
            return null;
        }

        return new PurchaseOrderLineSnapshot(
            id: $line->id,
            lineNumber: $line->line_number,
            itemCode: $line->item_code,
            quantityOrdered: (float) $line->quantity_ordered,
            unitPrice: (float) $line->unit_price,
            quantityReceived: $received[$line->id] ?? 0.0,
            quantityAlreadyMatched: $consumed[$line->id] ?? 0.0,
        );
    }
}
