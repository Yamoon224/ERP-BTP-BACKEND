<?php

namespace App\Domains\Matching\Engines;

use App\Domains\Matching\Contracts\MatchingEngineContract;
use App\Domains\Matching\Contracts\TolerancePolicyContract;
use App\Domains\Matching\DTOs\Discrepancy;
use App\Domains\Matching\DTOs\InvoiceLineInput;
use App\Domains\Matching\DTOs\InvoiceMatchInput;
use App\Domains\Matching\DTOs\LineOutcome;
use App\Domains\Matching\DTOs\MatchOutcome;
use App\Domains\Matching\DTOs\PurchaseOrderLineSnapshot;
use App\Domains\Matching\Enums\DiscrepancyType;
use App\Domains\Matching\Enums\MatchStatus;
use App\Domains\Shared\Support\Decimal;

/**
 * Rapprochement à 3 voies : bon de commande, bon de livraison, facture.
 *
 * Principe directeur : une portion de facture n'est payable que si elle est
 * couverte SIMULTANÉMENT par une quantité commandée, une quantité effectivement
 * réceptionnée sur un BL accepté, et un prix conforme au PO. Tout le reste est
 * soit non rapproché (en attente, sans droit à paiement), soit signalé pour
 * arbitrage humain. Le moteur ne rejette jamais une facture de lui-même et
 * n'accepte jamais un écart en silence (règle fonctionnelle n°6).
 *
 * **Multidevise.** Une facture peut être libellée dans une autre devise que son
 * bon de commande (EUR, USD, XOF). Les prix sont alors confrontés dans la devise
 * du PO — la référence contractuelle — au taux en vigueur à la date de la
 * facture. Les montants restent exprimés dans la devise de la facture, celle
 * dans laquelle le fournisseur sera réglé. Le moteur ne résout aucun taux
 * lui-même : ils lui sont fournis dans l'entrée, ce qui garde la décision
 * reproductible à l'identique.
 *
 * Cette classe est volontairement sans dépendance à la persistance : elle ne
 * lit que le InvoiceMatchInput qu'on lui remet, ce qui la rend testable
 * unitairement et rejouable sur des données archivées.
 */
final class ThreeWayMatchingEngine implements MatchingEngineContract
{
    public function __construct(
        private readonly TolerancePolicyContract $tolerancePolicy,
        private readonly string $engineVersion,
    ) {}

    public function version(): string
    {
        return $this->engineVersion;
    }

    public function evaluate(InvoiceMatchInput $input): MatchOutcome
    {
        $headerDiscrepancies = $this->checkHeader($input);

        // Un écart d'en-tête (mauvais fournisseur, taux introuvable, PO fermé)
        // vicie la facture entière : aucune ligne ne peut être rapprochée, et
        // aucun montant n'est autorisé tant qu'un humain n'a pas tranché.
        if ($headerDiscrepancies !== []) {
            return $this->blockedOutcome($input, $headerDiscrepancies);
        }

        $lineOutcomes = array_map(
            fn (InvoiceLineInput $line): LineOutcome => $this->evaluateLine($input, $line),
            $input->lines,
        );

        $invoicedAmount = $input->invoicedAmount();
        $matchedAmount = $input->invoiceCurrency->round(array_sum(array_map(
            fn (LineOutcome $outcome): float => $outcome->matchedAmount,
            $lineOutcomes,
        )));
        $unmatchedAmount = $input->invoiceCurrency->round($invoicedAmount - $matchedAmount);

        return new MatchOutcome(
            status: $this->aggregateStatus($lineOutcomes),
            currency: $input->invoiceCurrency,
            invoicedAmount: $invoicedAmount,
            matchedAmount: $matchedAmount,
            unmatchedAmount: $unmatchedAmount,
            lineOutcomes: $lineOutcomes,
            invoiceDiscrepancies: [],
            tolerance: $this->tolerancePolicy->snapshot($input),
            engineVersion: $this->engineVersion,
            baseCurrency: $input->baseCurrency,
            baseMatchedAmount: $input->toBaseCurrency($matchedAmount),
            baseUnmatchedAmount: $input->toBaseCurrency($unmatchedAmount),
            exchangeRateSnapshot: $input->exchangeRateSnapshot(),
        );
    }

    /**
     * Contrôles de cohérence au niveau de la facture. Ce sont les vérifications
     * qui rendent le contrôle à 3 voies utile contre la fraude : un faux
     * fournisseur, un PO réactivé après clôture ou un taux de change inconnu
     * sont des signaux forts, jamais des arrondis.
     *
     * @return list<Discrepancy>
     */
    private function checkHeader(InvoiceMatchInput $input): array
    {
        $discrepancies = [];

        if (! $input->supplierMatches()) {
            $discrepancies[] = new Discrepancy(
                type: DiscrepancyType::SupplierMismatch,
                message: sprintf(
                    'La facture %s est émise par un fournisseur différent de celui du bon de commande %s.',
                    $input->invoiceReference,
                    $input->purchaseOrderReference,
                ),
                context: [
                    'invoice_supplier_id' => $input->invoiceSupplierId,
                    'purchase_order_supplier_id' => $input->purchaseOrderSupplierId,
                ],
            );
        }

        // Devises différentes sans taux connu : on ne devine pas. Inventer un
        // taux reviendrait à inventer le montant qu'on s'apprête à autoriser.
        if (! $input->canCompareCurrencies()) {
            $discrepancies[] = new Discrepancy(
                type: DiscrepancyType::MissingExchangeRate,
                message: sprintf(
                    'Facture en %s contre bon de commande en %s : aucun taux de change connu pour les rapprocher.',
                    $input->invoiceCurrency->value,
                    $input->purchaseOrderCurrency->value,
                ),
                context: [
                    'invoice_currency' => $input->invoiceCurrency->value,
                    'purchase_order_currency' => $input->purchaseOrderCurrency->value,
                ],
            );
        }

        if (! $input->purchaseOrderAcceptsDocuments) {
            $discrepancies[] = new Discrepancy(
                type: DiscrepancyType::PurchaseOrderNotOpen,
                message: sprintf(
                    'Le bon de commande %s n\'est pas dans un état acceptant de nouvelles factures.',
                    $input->purchaseOrderReference,
                ),
                context: ['purchase_order_id' => $input->purchaseOrderId],
            );
        }

        return $discrepancies;
    }

    /**
     * Facture entièrement bloquée par un écart d'en-tête : on produit tout de
     * même un résultat par ligne (à zéro) pour que la trace reste complète et
     * lisible dans l'interface de revue.
     *
     * @param  list<Discrepancy>  $headerDiscrepancies
     */
    private function blockedOutcome(InvoiceMatchInput $input, array $headerDiscrepancies): MatchOutcome
    {
        $lineOutcomes = array_map(
            fn (InvoiceLineInput $line): LineOutcome => new LineOutcome(
                invoiceLineId: $line->id,
                purchaseOrderLineId: $line->purchaseOrderLine?->id,
                status: MatchStatus::Unmatched,
                quantityInvoiced: $line->quantity,
                quantityMatched: 0.0,
                quantityUnmatched: $line->quantity,
                unitPriceInvoiced: $line->unitPrice,
                unitPriceOrdered: $line->purchaseOrderLine?->unitPrice,
                priceVarianceRatio: null,
                matchedAmount: 0.0,
                evidence: [
                    'blocked_by_invoice_level_discrepancy' => true,
                    'discrepancy_types' => array_map(
                        fn (Discrepancy $discrepancy): string => $discrepancy->type->value,
                        $headerDiscrepancies,
                    ),
                ],
                discrepancies: [],
            ),
            $input->lines,
        );

        $invoicedAmount = $input->invoicedAmount();

        return new MatchOutcome(
            status: MatchStatus::Exception,
            currency: $input->invoiceCurrency,
            invoicedAmount: $invoicedAmount,
            matchedAmount: 0.0,
            unmatchedAmount: $invoicedAmount,
            lineOutcomes: $lineOutcomes,
            invoiceDiscrepancies: $headerDiscrepancies,
            tolerance: $this->tolerancePolicy->snapshot($input),
            engineVersion: $this->engineVersion,
            baseCurrency: $input->baseCurrency,
            baseMatchedAmount: 0.0,
            baseUnmatchedAmount: $input->toBaseCurrency($invoicedAmount),
            exchangeRateSnapshot: $input->exchangeRateSnapshot(),
        );
    }

    private function evaluateLine(InvoiceMatchInput $input, InvoiceLineInput $line): LineOutcome
    {
        $purchaseOrderLine = $line->purchaseOrderLine;

        if ($purchaseOrderLine === null) {
            return $this->orphanLineOutcome($line);
        }

        $tolerance = $this->tolerancePolicy->forLine($input, $line);
        $discrepancies = [];

        // --- Voie 1 : le prix ------------------------------------------------
        // La comparaison a lieu dans la devise du bon de commande : c'est elle
        // qui fait foi contractuellement.
        $comparablePrice = $line->comparableUnitPrice();
        $priceDelta = abs($comparablePrice - $purchaseOrderLine->unitPrice);
        $priceThreshold = $tolerance->absolutePriceThresholdFor($purchaseOrderLine->unitPrice);
        $priceWithinTolerance = ! Decimal::greaterThan($priceDelta, $priceThreshold);
        $priceOverridden = $line->hasApprovedOverrideFor(DiscrepancyType::PriceVariance);
        $priceVarianceRatio = Decimal::isZero($purchaseOrderLine->unitPrice)
            ? null
            : round(($comparablePrice - $purchaseOrderLine->unitPrice) / $purchaseOrderLine->unitPrice, 6);

        if (! $priceWithinTolerance && ! $priceOverridden) {
            $discrepancies[] = new Discrepancy(
                type: DiscrepancyType::PriceVariance,
                message: $this->priceVarianceMessage(
                    $input,
                    $line,
                    $purchaseOrderLine,
                    $comparablePrice,
                    $priceDelta,
                    $priceThreshold,
                ),
                context: [
                    'unit_price_invoiced' => $line->unitPrice,
                    'invoice_currency' => $input->invoiceCurrency->value,
                    'unit_price_invoiced_converted' => round($comparablePrice, 6),
                    'unit_price_ordered' => $purchaseOrderLine->unitPrice,
                    'comparison_currency' => $input->comparisonCurrency()->value,
                    'price_delta' => round($priceDelta, 6),
                    'price_variance_ratio' => $priceVarianceRatio,
                    'price_threshold' => round($priceThreshold, 6),
                    'exchange_rate' => $input->invoiceToComparisonRate?->rate,
                ],
                invoiceLineId: $line->id,
            );
        }

        // --- Voies 2 et 3 : la quantité --------------------------------------
        // Payable = min(commandé, reçu) − déjà rapproché par une autre facture.
        $quantityAvailable = $purchaseOrderLine->quantityAvailableForMatching();
        $quantityRemainingOnOrder = $purchaseOrderLine->quantityRemainingOnOrder();
        $quantityThreshold = $tolerance->absoluteQuantityThresholdFor($purchaseOrderLine->quantityOrdered);

        $overOrderedBy = $line->quantity - $quantityRemainingOnOrder;
        if (
            Decimal::greaterThan($overOrderedBy, $quantityThreshold)
            && ! $line->hasApprovedOverrideFor(DiscrepancyType::QuantityOverOrdered)
        ) {
            $discrepancies[] = new Discrepancy(
                type: DiscrepancyType::QuantityOverOrdered,
                message: sprintf(
                    'Ligne %d : %s facturé(s) pour %s encore commandé(s) sur le bon de commande.',
                    $line->lineNumber,
                    $this->formatNumber($line->quantity),
                    $this->formatNumber($quantityRemainingOnOrder),
                ),
                context: [
                    'quantity_invoiced' => $line->quantity,
                    'quantity_remaining_on_order' => $quantityRemainingOnOrder,
                    'quantity_ordered' => $purchaseOrderLine->quantityOrdered,
                    'quantity_already_matched' => $purchaseOrderLine->quantityAlreadyMatched,
                    'over_ordered_by' => round($overOrderedBy, 3),
                ],
                invoiceLineId: $line->id,
            );
        }

        // Sur-livraison : détectée ici parce que c'est au moment de payer
        // qu'elle devient un risque financier, même si elle naît à la réception.
        $overDeliveredBy = $purchaseOrderLine->quantityReceived - $purchaseOrderLine->quantityOrdered;
        if (Decimal::greaterThan($overDeliveredBy, $quantityThreshold)) {
            $discrepancies[] = new Discrepancy(
                type: DiscrepancyType::QuantityOverDelivered,
                message: sprintf(
                    'Ligne %d : %s reçu(s) pour %s commandé(s) sur le bon de commande.',
                    $line->lineNumber,
                    $this->formatNumber($purchaseOrderLine->quantityReceived),
                    $this->formatNumber($purchaseOrderLine->quantityOrdered),
                ),
                context: [
                    'quantity_received' => $purchaseOrderLine->quantityReceived,
                    'quantity_ordered' => $purchaseOrderLine->quantityOrdered,
                    'over_delivered_by' => round($overDeliveredBy, 3),
                ],
                invoiceLineId: $line->id,
            );
        }

        // Un écart de prix non arbitré gèle toute la ligne : on ne sait pas à
        // quel prix payer, donc on ne paie rien. Un écart de quantité, lui,
        // laisse passer la portion saine (règle fonctionnelle n°4).
        $priceBlocks = ! $priceWithinTolerance && ! $priceOverridden;
        $quantityMatched = $priceBlocks
            ? 0.0
            : Decimal::quantity(min($line->quantity, $quantityAvailable));

        // Le montant payable est calculé au prix FACTURÉ, dans la devise de la
        // facture : c'est ce que le fournisseur recevra.
        $matchedAmount = $input->invoiceCurrency->round($quantityMatched * $line->unitPrice);

        return new LineOutcome(
            invoiceLineId: $line->id,
            purchaseOrderLineId: $purchaseOrderLine->id,
            status: $this->lineStatus($line, $quantityMatched, $discrepancies),
            quantityInvoiced: $line->quantity,
            quantityMatched: $quantityMatched,
            quantityUnmatched: Decimal::quantity($line->quantity - $quantityMatched),
            unitPriceInvoiced: $line->unitPrice,
            unitPriceOrdered: $purchaseOrderLine->unitPrice,
            priceVarianceRatio: $priceVarianceRatio,
            matchedAmount: $matchedAmount,
            evidence: $this->buildEvidence(
                $input,
                $line,
                $purchaseOrderLine,
                $comparablePrice,
                $quantityAvailable,
                $quantityRemainingOnOrder,
                $priceDelta,
                $priceThreshold,
                $priceWithinTolerance,
                $priceOverridden,
                $quantityThreshold,
            ),
            discrepancies: $discrepancies,
        );
    }

    /**
     * Un écart de prix sur une facture convertie doit citer les deux devises :
     * « 612 USD » et « 540 EUR » ne se comparent pas de tête, le relecteur a
     * besoin de voir le montant converti et le taux appliqué.
     */
    private function priceVarianceMessage(
        InvoiceMatchInput $input,
        InvoiceLineInput $line,
        PurchaseOrderLineSnapshot $purchaseOrderLine,
        float $comparablePrice,
        float $priceDelta,
        float $priceThreshold,
    ): string {
        $comparison = $input->comparisonCurrency();

        if (! $input->requiresConversion()) {
            return sprintf(
                'Ligne %d : prix unitaire facturé %s %s contre %s %s au bon de commande (écart %s, tolérance %s).',
                $line->lineNumber,
                $this->formatNumber($line->unitPrice),
                $comparison->value,
                $this->formatNumber($purchaseOrderLine->unitPrice),
                $comparison->value,
                $this->formatNumber($priceDelta),
                $this->formatNumber($priceThreshold),
            );
        }

        return sprintf(
            'Ligne %d : prix unitaire facturé %s %s, soit %s %s au taux de %s, contre %s %s au bon de commande (écart %s %s, tolérance %s %s).',
            $line->lineNumber,
            $this->formatNumber($line->unitPrice),
            $input->invoiceCurrency->value,
            $this->formatNumber($comparablePrice),
            $comparison->value,
            $this->formatNumber($input->invoiceToComparisonRate->rate ?? 1.0),
            $this->formatNumber($purchaseOrderLine->unitPrice),
            $comparison->value,
            $this->formatNumber($priceDelta),
            $comparison->value,
            $this->formatNumber($priceThreshold),
            $comparison->value,
        );
    }

    /**
     * Ligne de facture qui ne pointe vers aucune ligne de PO : impossible de
     * rapprocher quoi que ce soit, et c'est exactement le vecteur d'une
     * prestation ajoutée après coup. Revue humaine systématique.
     */
    private function orphanLineOutcome(InvoiceLineInput $line): LineOutcome
    {
        $discrepancy = new Discrepancy(
            type: DiscrepancyType::MissingPurchaseOrderLine,
            message: sprintf(
                'Ligne %d (%s) ne référence aucune ligne du bon de commande.',
                $line->lineNumber,
                $line->description,
            ),
            context: [
                'description' => $line->description,
                'quantity' => $line->quantity,
                'unit_price' => $line->unitPrice,
            ],
            invoiceLineId: $line->id,
        );

        return new LineOutcome(
            invoiceLineId: $line->id,
            purchaseOrderLineId: null,
            status: MatchStatus::Exception,
            quantityInvoiced: $line->quantity,
            quantityMatched: 0.0,
            quantityUnmatched: $line->quantity,
            unitPriceInvoiced: $line->unitPrice,
            unitPriceOrdered: null,
            priceVarianceRatio: null,
            matchedAmount: 0.0,
            evidence: ['purchase_order_line' => null],
            discrepancies: [$discrepancy],
        );
    }

    /**
     * Preuve chiffrée conservée avec le résultat : tous les agrégats ayant
     * servi au calcul, conversion comprise, pour que la décision reste
     * explicable même après évolution du PO, des livraisons ou des taux
     * (règle fonctionnelle n°5).
     *
     * @return array<string, mixed>
     */
    private function buildEvidence(
        InvoiceMatchInput $input,
        InvoiceLineInput $line,
        PurchaseOrderLineSnapshot $purchaseOrderLine,
        float $comparablePrice,
        float $quantityAvailable,
        float $quantityRemainingOnOrder,
        float $priceDelta,
        float $priceThreshold,
        bool $priceWithinTolerance,
        bool $priceOverridden,
        float $quantityThreshold,
    ): array {
        return [
            'purchase_order_line_id' => $purchaseOrderLine->id,
            'purchase_order_line_number' => $purchaseOrderLine->lineNumber,
            'item_code' => $purchaseOrderLine->itemCode,
            'quantity_ordered' => $purchaseOrderLine->quantityOrdered,
            'quantity_received' => $purchaseOrderLine->quantityReceived,
            'quantity_already_matched' => $purchaseOrderLine->quantityAlreadyMatched,
            'quantity_available_for_matching' => round($quantityAvailable, 3),
            'quantity_remaining_on_order' => round($quantityRemainingOnOrder, 3),
            'quantity_invoiced' => $line->quantity,
            'quantity_tolerance_threshold' => round($quantityThreshold, 3),

            'invoice_currency' => $input->invoiceCurrency->value,
            'comparison_currency' => $input->comparisonCurrency()->value,
            'conversion_applied' => $input->requiresConversion(),
            'exchange_rate' => $input->invoiceToComparisonRate?->rate,
            'exchange_rate_source' => $input->invoiceToComparisonRate?->source->value,
            'exchange_rate_effective_from' => $input->invoiceToComparisonRate?->effectiveFrom,

            'unit_price_ordered' => $purchaseOrderLine->unitPrice,
            'unit_price_invoiced' => $line->unitPrice,
            'unit_price_invoiced_in_comparison_currency' => round($comparablePrice, 6),
            'price_delta' => round($priceDelta, 6),
            'price_tolerance_threshold' => round($priceThreshold, 6),
            'price_within_tolerance' => $priceWithinTolerance,
            'price_override_approved' => $priceOverridden,
            'approved_overrides' => array_map(
                fn (DiscrepancyType $type): string => $type->value,
                $line->approvedOverrides,
            ),
        ];
    }

    /** @param  list<Discrepancy>  $discrepancies */
    private function lineStatus(InvoiceLineInput $line, float $quantityMatched, array $discrepancies): MatchStatus
    {
        if ($discrepancies !== []) {
            return MatchStatus::Exception;
        }

        if (Decimal::equals($quantityMatched, $line->quantity)) {
            return MatchStatus::Matched;
        }

        return Decimal::isZero($quantityMatched) ? MatchStatus::Unmatched : MatchStatus::PartiallyMatched;
    }

    /**
     * Statut global : l'exception prime (elle appelle une action humaine),
     * sinon on résume l'avancement du rapprochement.
     *
     * @param  list<LineOutcome>  $lineOutcomes
     */
    private function aggregateStatus(array $lineOutcomes): MatchStatus
    {
        if ($lineOutcomes === []) {
            return MatchStatus::Unmatched;
        }

        $statuses = array_map(fn (LineOutcome $outcome): MatchStatus => $outcome->status, $lineOutcomes);

        if (in_array(MatchStatus::Exception, $statuses, true)) {
            return MatchStatus::Exception;
        }

        $isOnly = fn (MatchStatus $only): bool => array_filter(
            $statuses,
            fn (MatchStatus $status): bool => $status !== $only,
        ) === [];

        if ($isOnly(MatchStatus::Matched)) {
            return MatchStatus::Matched;
        }

        if ($isOnly(MatchStatus::Unmatched)) {
            return MatchStatus::Unmatched;
        }

        return MatchStatus::PartiallyMatched;
    }

    private function formatNumber(float $value): string
    {
        $formatted = number_format($value, 4, ',', ' ');

        return str_contains($formatted, ',') ? rtrim(rtrim($formatted, '0'), ',') : $formatted;
    }
}
