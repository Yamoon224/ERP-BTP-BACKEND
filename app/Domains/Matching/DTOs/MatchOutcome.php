<?php

namespace App\Domains\Matching\DTOs;

use App\Domains\Matching\Enums\MatchStatus;
use App\Domains\Shared\Enums\Currency;

/**
 * Résultat complet d'un rapprochement, avant toute persistance.
 *
 * Les montants sont exprimés dans la **devise de la facture** — c'est celle
 * dans laquelle le fournisseur a facturé et sera payé. Les mêmes montants
 * convertis dans la devise de référence accompagnent le résultat, uniquement
 * pour l'agrégation (un tableau de bord qui additionne des euros, des dollars
 * et des francs CFA n'affiche rien de sensé).
 */
final readonly class MatchOutcome
{
    /**
     * @param  list<LineOutcome>  $lineOutcomes
     * @param  list<Discrepancy>  $invoiceDiscrepancies  Écarts portant sur la facture entière.
     * @param  array<string, mixed>  $exchangeRateSnapshot  Taux appliqués, archivés avec la décision.
     */
    public function __construct(
        public MatchStatus $status,
        public Currency $currency,
        public float $invoicedAmount,
        public float $matchedAmount,
        public float $unmatchedAmount,
        public array $lineOutcomes,
        public array $invoiceDiscrepancies,
        public Tolerance $tolerance,
        public string $engineVersion,
        public Currency $baseCurrency = Currency::EUR,
        public float $baseMatchedAmount = 0.0,
        public float $baseUnmatchedAmount = 0.0,
        public array $exchangeRateSnapshot = [],
    ) {}

    /** @return list<Discrepancy> */
    public function allDiscrepancies(): array
    {
        $discrepancies = $this->invoiceDiscrepancies;

        foreach ($this->lineOutcomes as $lineOutcome) {
            foreach ($lineOutcome->discrepancies as $discrepancy) {
                $discrepancies[] = $discrepancy;
            }
        }

        return $discrepancies;
    }

    public function exceptionCount(): int
    {
        return count($this->allDiscrepancies());
    }

    /**
     * Montant autorisable au paiement, dans la devise de la facture.
     *
     * C'est exactement le montant rapproché : par construction, le moteur n'y
     * met que la portion couverte à la fois par le PO et par un BL accepté
     * (règle fonctionnelle n°4). Une facture qui porte par ailleurs un écart en
     * revue reste donc payable pour sa portion saine — signaler un écart ne
     * doit pas geler la part légitime de la créance fournisseur.
     */
    public function authorizedAmount(): float
    {
        return $this->matchedAmount;
    }
}
