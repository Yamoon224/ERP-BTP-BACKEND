<?php

namespace Tests\Unit\Matching;

use App\Domains\Matching\DTOs\InvoiceLineInput;
use App\Domains\Matching\DTOs\InvoiceMatchInput;
use App\Domains\Matching\DTOs\PurchaseOrderLineSnapshot;
use App\Domains\Matching\Enums\DiscrepancyType;
use App\Domains\Shared\DTOs\ExchangeRate;
use App\Domains\Shared\Enums\Currency;
use App\Domains\Shared\Enums\ExchangeRateSource;

/**
 * Constructeur d'entrées de test pour le moteur.
 *
 * Chaque test ne décrit que ce qui l'intéresse (« 100 commandés, 40 reçus,
 * 100 facturés ») ; tout le reste tient dans des valeurs par défaut cohérentes.
 * Sans ça, chaque cas de test recopierait vingt lignes de DTO et l'intention
 * disparaîtrait sous le bruit.
 */
final class MatchInputBuilder
{
    /** @var list<InvoiceLineInput> */
    private array $lines = [];

    private int $invoiceSupplierId = 1;

    private int $purchaseOrderSupplierId = 1;

    private Currency $invoiceCurrency = Currency::EUR;

    private Currency $purchaseOrderCurrency = Currency::EUR;

    private Currency $baseCurrency = Currency::EUR;

    /** Taux facture → devise du bon de commande ; null = aucun taux connu. */
    private ?float $invoiceToComparisonRate = null;

    private ?float $invoiceToBaseRate = null;

    private ?float $baseToComparisonRate = null;

    private bool $purchaseOrderAcceptsDocuments = true;

    private int $nextLineNumber = 1;

    public static function make(): self
    {
        return new self;
    }

    public function withSupplierMismatch(): self
    {
        $clone = clone $this;
        $clone->invoiceSupplierId = 999;

        return $clone;
    }

    /**
     * Facture libellée dans une autre devise que le bon de commande, avec le
     * taux applicable. Omettre `$rate` simule un taux introuvable.
     */
    public function withInvoiceCurrency(Currency $currency, ?float $rateToComparison = null, ?float $rateToBase = null): self
    {
        $clone = clone $this;
        $clone->invoiceCurrency = $currency;
        $clone->invoiceToComparisonRate = $rateToComparison;
        $clone->invoiceToBaseRate = $rateToBase ?? $rateToComparison;

        return $clone;
    }

    /** Bon de commande libellé dans une devise donnée (référence de comparaison). */
    public function withPurchaseOrderCurrency(Currency $currency, ?float $baseToComparisonRate = null): self
    {
        $clone = clone $this;
        $clone->purchaseOrderCurrency = $currency;
        $clone->baseToComparisonRate = $baseToComparisonRate;

        return $clone;
    }

    public function withClosedPurchaseOrder(): self
    {
        $clone = clone $this;
        $clone->purchaseOrderAcceptsDocuments = false;

        return $clone;
    }

    /**
     * Ajoute une ligne de facture adossée à une ligne de PO.
     *
     * @param  list<DiscrepancyType>  $approvedOverrides
     */
    public function withLine(
        float $quantityOrdered,
        float $unitPriceOrdered,
        float $quantityReceived,
        float $quantityInvoiced,
        float $unitPriceInvoiced,
        float $quantityAlreadyMatched = 0.0,
        array $approvedOverrides = [],
    ): self {
        $clone = clone $this;
        $lineNumber = $clone->nextLineNumber++;

        $clone->lines[] = new InvoiceLineInput(
            id: $lineNumber,
            lineNumber: $lineNumber,
            description: "Article {$lineNumber}",
            quantity: $quantityInvoiced,
            unitPrice: $unitPriceInvoiced,
            purchaseOrderLine: new PurchaseOrderLineSnapshot(
                id: 100 + $lineNumber,
                lineNumber: $lineNumber,
                itemCode: "ART-{$lineNumber}",
                quantityOrdered: $quantityOrdered,
                unitPrice: $unitPriceOrdered,
                quantityReceived: $quantityReceived,
                quantityAlreadyMatched: $quantityAlreadyMatched,
            ),
            // Le prix converti est recalculé au moment du build, quand le taux
            // est connu (les appels peuvent survenir dans n'importe quel ordre).
            unitPriceInComparisonCurrency: null,
            approvedOverrides: $approvedOverrides,
        );

        return $clone;
    }

    /** Ligne de facture ne référençant aucune ligne de bon de commande. */
    public function withOrphanLine(float $quantity, float $unitPrice): self
    {
        $clone = clone $this;
        $lineNumber = $clone->nextLineNumber++;

        $clone->lines[] = new InvoiceLineInput(
            id: $lineNumber,
            lineNumber: $lineNumber,
            description: "Prestation hors commande {$lineNumber}",
            quantity: $quantity,
            unitPrice: $unitPrice,
            purchaseOrderLine: null,
        );

        return $clone;
    }

    public function build(): InvoiceMatchInput
    {
        $needsConversion = $this->invoiceCurrency !== $this->purchaseOrderCurrency;
        $comparisonRate = $needsConversion ? $this->rate($this->invoiceCurrency, $this->purchaseOrderCurrency, $this->invoiceToComparisonRate) : null;

        $lines = array_map(
            fn (InvoiceLineInput $line): InvoiceLineInput => new InvoiceLineInput(
                id: $line->id,
                lineNumber: $line->lineNumber,
                description: $line->description,
                quantity: $line->quantity,
                unitPrice: $line->unitPrice,
                purchaseOrderLine: $line->purchaseOrderLine,
                unitPriceInComparisonCurrency: $comparisonRate?->convert($line->unitPrice),
                approvedOverrides: $line->approvedOverrides,
            ),
            $this->lines,
        );

        return new InvoiceMatchInput(
            invoiceId: 1,
            invoiceReference: 'FAC-TEST-001',
            invoiceSupplierId: $this->invoiceSupplierId,
            invoiceCurrency: $this->invoiceCurrency,
            purchaseOrderId: 1,
            purchaseOrderReference: 'PO-TEST-001',
            purchaseOrderSupplierId: $this->purchaseOrderSupplierId,
            purchaseOrderCurrency: $this->purchaseOrderCurrency,
            purchaseOrderAcceptsDocuments: $this->purchaseOrderAcceptsDocuments,
            lines: $lines,
            invoiceToComparisonRate: $comparisonRate,
            invoiceToBaseRate: $this->rate($this->invoiceCurrency, $this->baseCurrency, $this->invoiceToBaseRate),
            baseToComparisonRate: $this->rate($this->baseCurrency, $this->purchaseOrderCurrency, $this->baseToComparisonRate),
            baseCurrency: $this->baseCurrency,
        );
    }

    private function rate(Currency $from, Currency $to, ?float $value): ?ExchangeRate
    {
        if ($from === $to) {
            return ExchangeRate::identity($from);
        }

        if ($value === null) {
            return null;
        }

        return new ExchangeRate($from, $to, $value, ExchangeRateSource::Manual, '2026-01-01');
    }
}
