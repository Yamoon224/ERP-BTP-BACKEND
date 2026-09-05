<?php

namespace App\Domains\Matching\DTOs;

use App\Domains\Shared\DTOs\ExchangeRate;
use App\Domains\Shared\Enums\Currency;

/**
 * Photographie complète des trois voies (PO, BL, facture) au moment du
 * rapprochement, taux de change compris.
 *
 * Assemblée par MatchInputAssembler depuis la base, puis consommée par un
 * moteur qui n'a plus aucune dépendance à la persistance — y compris pour les
 * conversions : les taux sont résolus une fois, ici, et transportés avec les
 * données. Le moteur ne « va pas chercher » un taux au milieu d'un calcul.
 *
 * Trois devises coexistent, et les confondre serait une erreur :
 *  - **facture**    : ce que le fournisseur réclame, et ce qui lui sera payé ;
 *  - **comparaison**: celle du bon de commande, référence contractuelle des prix ;
 *  - **référence**  : devise unique d'agrégation pour le pilotage.
 */
final readonly class InvoiceMatchInput
{
    /**
     * @param  list<InvoiceLineInput>  $lines
     * @param  ExchangeRate|null  $invoiceToComparisonRate  null si aucun taux n'est connu — le
     *                                                      moteur bloque alors la facture.
     */
    public function __construct(
        public int $invoiceId,
        public string $invoiceReference,
        public int $invoiceSupplierId,
        public Currency $invoiceCurrency,
        public int $purchaseOrderId,
        public string $purchaseOrderReference,
        public int $purchaseOrderSupplierId,
        public Currency $purchaseOrderCurrency,
        public bool $purchaseOrderAcceptsDocuments,
        public array $lines,
        public ?ExchangeRate $invoiceToComparisonRate = null,
        public ?ExchangeRate $invoiceToBaseRate = null,
        public ?ExchangeRate $baseToComparisonRate = null,
        public Currency $baseCurrency = Currency::EUR,
    ) {}

    /** Devise dans laquelle les prix sont confrontés : celle du bon de commande. */
    public function comparisonCurrency(): Currency
    {
        return $this->purchaseOrderCurrency;
    }

    public function supplierMatches(): bool
    {
        return $this->invoiceSupplierId === $this->purchaseOrderSupplierId;
    }

    public function requiresConversion(): bool
    {
        return $this->invoiceCurrency !== $this->purchaseOrderCurrency;
    }

    /**
     * Les prix de la facture peuvent-ils être confrontés à ceux du bon de
     * commande ? Faux uniquement quand une conversion est nécessaire et
     * qu'aucun taux n'est connu.
     */
    public function canCompareCurrencies(): bool
    {
        return ! $this->requiresConversion() || $this->invoiceToComparisonRate !== null;
    }

    /** Montant facturé, dans la devise de la facture. */
    public function invoicedAmount(): float
    {
        return $this->invoiceCurrency->round(array_sum(array_map(
            fn (InvoiceLineInput $line): float => $line->invoicedAmount(),
            $this->lines,
        )));
    }

    /** Convertit un montant de la devise de facture vers la devise de référence. */
    public function toBaseCurrency(float $amount): float
    {
        if ($this->invoiceToBaseRate === null) {
            return $this->baseCurrency->round($amount);
        }

        return $this->baseCurrency->round($this->invoiceToBaseRate->convert($amount));
    }

    /**
     * Taux appliqués, archivés dans l'exécution. Une décision impliquant une
     * conversion n'est explicable que si l'on sait à quel taux elle a été prise.
     *
     * @return array<string, mixed>
     */
    public function exchangeRateSnapshot(): array
    {
        return array_filter([
            'invoice_currency' => $this->invoiceCurrency->value,
            'comparison_currency' => $this->comparisonCurrency()->value,
            'base_currency' => $this->baseCurrency->value,
            'invoice_to_comparison' => $this->invoiceToComparisonRate?->toArray(),
            'invoice_to_base' => $this->invoiceToBaseRate?->toArray(),
            'base_to_comparison' => $this->baseToComparisonRate?->toArray(),
        ], fn ($value): bool => $value !== null);
    }
}
