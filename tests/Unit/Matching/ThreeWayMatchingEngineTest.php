<?php

namespace Tests\Unit\Matching;

use App\Domains\Matching\Contracts\TolerancePolicyContract;
use App\Domains\Matching\DTOs\Tolerance;
use App\Domains\Matching\Engines\ThreeWayMatchingEngine;
use App\Domains\Matching\Enums\DiscrepancyType;
use App\Domains\Matching\Enums\MatchStatus;
use App\Domains\Matching\Policies\ConfiguredTolerancePolicy;
use App\Domains\Shared\Enums\Currency;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires du coeur métier. Aucune base de données, aucun conteneur
 * Laravel : le moteur ne dépend que de ses entrées, ces tests s'exécutent en
 * quelques millisecondes et décrivent la règle de gestion en langage métier.
 */
class ThreeWayMatchingEngineTest extends TestCase
{
    private function engine(
        float $priceRatio = 0.01,
        float $priceAbsolute = 0.5,
        float $quantityRatio = 0.0,
        float $quantityAbsolute = 0.0,
    ): ThreeWayMatchingEngine {
        $tolerance = new Tolerance($priceRatio, $priceAbsolute, $quantityRatio, $quantityAbsolute, Currency::EUR);

        return new ThreeWayMatchingEngine(
            $this->tolerancePolicy($tolerance),
            '1.0.0-test',
        );
    }

    private function tolerancePolicy(Tolerance $tolerance): TolerancePolicyContract
    {
        return new ConfiguredTolerancePolicy($tolerance);
    }

    // --- Cas nominal ------------------------------------------------------

    #[Test]
    public function it_fully_matches_when_ordered_received_and_invoiced_agree(): void
    {
        $input = MatchInputBuilder::make()
            ->withLine(quantityOrdered: 100, unitPriceOrdered: 10, quantityReceived: 100, quantityInvoiced: 100, unitPriceInvoiced: 10)
            ->build();

        $outcome = $this->engine()->evaluate($input);

        $this->assertSame(MatchStatus::Matched, $outcome->status);
        $this->assertSame(1000.0, $outcome->matchedAmount);
        $this->assertSame(0.0, $outcome->unmatchedAmount);
        $this->assertSame([], $outcome->allDiscrepancies());
    }

    // --- Règle n°4 : paiement limité à la portion rapprochée ---------------

    #[Test]
    public function it_only_authorises_the_received_portion_when_delivery_is_partial(): void
    {
        // 100 commandés et facturés, mais seulement 40 réellement livrés.
        $input = MatchInputBuilder::make()
            ->withLine(quantityOrdered: 100, unitPriceOrdered: 10, quantityReceived: 40, quantityInvoiced: 100, unitPriceInvoiced: 10)
            ->build();

        $outcome = $this->engine()->evaluate($input);

        $this->assertSame(MatchStatus::PartiallyMatched, $outcome->status);
        $this->assertSame(400.0, $outcome->matchedAmount);
        $this->assertSame(600.0, $outcome->unmatchedAmount);
        // Une livraison en retard n'est pas un écart : rien à arbitrer, la
        // facture attend simplement la marchandise.
        $this->assertSame([], $outcome->allDiscrepancies());
    }

    #[Test]
    public function it_authorises_nothing_when_no_delivery_has_been_received(): void
    {
        $input = MatchInputBuilder::make()
            ->withLine(quantityOrdered: 100, unitPriceOrdered: 10, quantityReceived: 0, quantityInvoiced: 100, unitPriceInvoiced: 10)
            ->build();

        $outcome = $this->engine()->evaluate($input);

        $this->assertSame(MatchStatus::Unmatched, $outcome->status);
        $this->assertSame(0.0, $outcome->matchedAmount);
        $this->assertSame(1000.0, $outcome->unmatchedAmount);
    }

    #[Test]
    public function it_caps_matching_at_the_ordered_quantity_even_when_more_was_delivered(): void
    {
        // Sur-livraison : 130 reçus pour 100 commandés. Le PO reste le plafond.
        $input = MatchInputBuilder::make()
            ->withLine(quantityOrdered: 100, unitPriceOrdered: 10, quantityReceived: 130, quantityInvoiced: 100, unitPriceInvoiced: 10)
            ->build();

        $outcome = $this->engine()->evaluate($input);

        $this->assertSame(1000.0, $outcome->matchedAmount);
        $this->assertSame(
            [DiscrepancyType::QuantityOverDelivered],
            array_map(fn ($discrepancy) => $discrepancy->type, $outcome->allDiscrepancies()),
        );
    }

    // --- Anti-double paiement ---------------------------------------------

    #[Test]
    public function it_excludes_quantities_already_matched_by_another_invoice(): void
    {
        // 100 reçus, dont 70 déjà rapprochés par une facture antérieure :
        // seuls 30 restent payables, même si la nouvelle facture en réclame 100.
        $input = MatchInputBuilder::make()
            ->withLine(
                quantityOrdered: 100,
                unitPriceOrdered: 10,
                quantityReceived: 100,
                quantityInvoiced: 100,
                unitPriceInvoiced: 10,
                quantityAlreadyMatched: 70,
            )
            ->build();

        $outcome = $this->engine()->evaluate($input);

        $this->assertSame(300.0, $outcome->matchedAmount);
        $this->assertSame(700.0, $outcome->unmatchedAmount);
    }

    #[Test]
    public function it_authorises_nothing_when_the_whole_delivery_is_already_paid_for(): void
    {
        $input = MatchInputBuilder::make()
            ->withLine(
                quantityOrdered: 100,
                unitPriceOrdered: 10,
                quantityReceived: 100,
                quantityInvoiced: 100,
                unitPriceInvoiced: 10,
                quantityAlreadyMatched: 100,
            )
            ->build();

        $outcome = $this->engine()->evaluate($input);

        $this->assertSame(0.0, $outcome->matchedAmount);
        // La quantité restant commandable est nulle : facturer à nouveau est
        // une sur-facturation, pas une simple attente de livraison.
        $this->assertContains(
            DiscrepancyType::QuantityOverOrdered,
            array_map(fn ($discrepancy) => $discrepancy->type, $outcome->allDiscrepancies()),
        );
    }

    // --- Règle n°6 : écarts de prix ---------------------------------------

    #[Test]
    public function it_accepts_a_price_difference_inside_the_tolerance(): void
    {
        // 10,05 contre 10,00 : +0,5 %, sous le seuil de 1 %.
        $input = MatchInputBuilder::make()
            ->withLine(quantityOrdered: 100, unitPriceOrdered: 10, quantityReceived: 100, quantityInvoiced: 100, unitPriceInvoiced: 10.05)
            ->build();

        $outcome = $this->engine(priceRatio: 0.01, priceAbsolute: 0.0)->evaluate($input);

        $this->assertSame(MatchStatus::Matched, $outcome->status);
        // Dans la tolérance, c'est le prix facturé qui est payé.
        $this->assertSame(1005.0, $outcome->matchedAmount);
    }

    #[Test]
    public function it_flags_a_price_difference_beyond_the_tolerance_and_pays_nothing_on_that_line(): void
    {
        $input = MatchInputBuilder::make()
            ->withLine(quantityOrdered: 100, unitPriceOrdered: 10, quantityReceived: 100, quantityInvoiced: 100, unitPriceInvoiced: 12)
            ->build();

        $outcome = $this->engine()->evaluate($input);

        $this->assertSame(MatchStatus::Exception, $outcome->status);
        $this->assertSame(0.0, $outcome->matchedAmount, 'Un prix non conforme ne doit rien libérer.');
        $this->assertSame(DiscrepancyType::PriceVariance, $outcome->allDiscrepancies()[0]->type);
        $this->assertSame(0.2, $outcome->lineOutcomes[0]->priceVarianceRatio);
    }

    #[Test]
    public function it_flags_an_under_charge_beyond_the_tolerance_too(): void
    {
        // Une facture moins chère que le PO n'est pas un risque de paiement,
        // mais reste une incohérence documentaire : elle doit être vue.
        $input = MatchInputBuilder::make()
            ->withLine(quantityOrdered: 100, unitPriceOrdered: 10, quantityReceived: 100, quantityInvoiced: 100, unitPriceInvoiced: 7)
            ->build();

        $outcome = $this->engine()->evaluate($input);

        $this->assertSame(MatchStatus::Exception, $outcome->status);
        $this->assertSame(DiscrepancyType::PriceVariance, $outcome->allDiscrepancies()[0]->type);
    }

    #[Test]
    public function it_uses_the_absolute_threshold_for_low_unit_prices(): void
    {
        // 0,80 contre 0,50 : +60 % en relatif, mais 0,30 en absolu, sous le
        // seuil absolu de 0,50. La tolérance retenue est la plus permissive.
        $input = MatchInputBuilder::make()
            ->withLine(quantityOrdered: 1000, unitPriceOrdered: 0.50, quantityReceived: 1000, quantityInvoiced: 1000, unitPriceInvoiced: 0.80)
            ->build();

        $outcome = $this->engine(priceRatio: 0.01, priceAbsolute: 0.5)->evaluate($input);

        $this->assertSame(MatchStatus::Matched, $outcome->status);
        $this->assertSame(800.0, $outcome->matchedAmount);
    }

    #[Test]
    public function it_matches_a_price_variance_line_once_the_variance_has_been_approved(): void
    {
        $input = MatchInputBuilder::make()
            ->withLine(
                quantityOrdered: 100,
                unitPriceOrdered: 10,
                quantityReceived: 100,
                quantityInvoiced: 100,
                unitPriceInvoiced: 12,
                approvedOverrides: [DiscrepancyType::PriceVariance],
            )
            ->build();

        $outcome = $this->engine()->evaluate($input);

        $this->assertSame(MatchStatus::Matched, $outcome->status);
        $this->assertSame(1200.0, $outcome->matchedAmount);
        $this->assertSame([], $outcome->allDiscrepancies());
    }

    // --- Règle n°6 : écarts de quantité -----------------------------------

    #[Test]
    public function it_pays_the_clean_portion_and_flags_the_over_invoiced_excess(): void
    {
        // 120 facturés pour 100 commandés et reçus : 100 sont payables, les 20
        // excédentaires partent en revue au lieu d'être payés ou rejetés en bloc.
        $input = MatchInputBuilder::make()
            ->withLine(quantityOrdered: 100, unitPriceOrdered: 10, quantityReceived: 100, quantityInvoiced: 120, unitPriceInvoiced: 10)
            ->build();

        $outcome = $this->engine()->evaluate($input);

        $this->assertSame(MatchStatus::Exception, $outcome->status);
        $this->assertSame(1000.0, $outcome->matchedAmount);
        $this->assertSame(200.0, $outcome->unmatchedAmount);
        $this->assertSame(DiscrepancyType::QuantityOverOrdered, $outcome->allDiscrepancies()[0]->type);
    }

    #[Test]
    public function it_tolerates_a_small_quantity_excess_when_configured_to(): void
    {
        // Matières en vrac : 2 % de dépassement accepté sans revue.
        $input = MatchInputBuilder::make()
            ->withLine(quantityOrdered: 100, unitPriceOrdered: 10, quantityReceived: 101, quantityInvoiced: 101, unitPriceInvoiced: 10)
            ->build();

        $outcome = $this->engine(quantityRatio: 0.02)->evaluate($input);

        $this->assertSame([], $outcome->allDiscrepancies());
        // Le paiement reste malgré tout plafonné au commandé : tolérer un écart
        // n'est pas élargir la commande.
        $this->assertSame(1000.0, $outcome->matchedAmount);
    }

    // --- Règle n°6 : cohérence documentaire -------------------------------

    #[Test]
    public function it_blocks_the_whole_invoice_when_the_supplier_does_not_match(): void
    {
        $input = MatchInputBuilder::make()
            ->withLine(quantityOrdered: 100, unitPriceOrdered: 10, quantityReceived: 100, quantityInvoiced: 100, unitPriceInvoiced: 10)
            ->withSupplierMismatch()
            ->build();

        $outcome = $this->engine()->evaluate($input);

        $this->assertSame(MatchStatus::Exception, $outcome->status);
        $this->assertSame(0.0, $outcome->matchedAmount);
        $this->assertSame(DiscrepancyType::SupplierMismatch, $outcome->invoiceDiscrepancies[0]->type);
    }

    #[Test]
    public function it_blocks_the_whole_invoice_when_the_purchase_order_is_closed(): void
    {
        $input = MatchInputBuilder::make()
            ->withLine(quantityOrdered: 100, unitPriceOrdered: 10, quantityReceived: 100, quantityInvoiced: 100, unitPriceInvoiced: 10)
            ->withClosedPurchaseOrder()
            ->build();

        $outcome = $this->engine()->evaluate($input);

        $this->assertSame(MatchStatus::Exception, $outcome->status);
        $this->assertSame(0.0, $outcome->matchedAmount);
        $this->assertSame(DiscrepancyType::PurchaseOrderNotOpen, $outcome->invoiceDiscrepancies[0]->type);
    }

    #[Test]
    public function it_flags_an_invoice_line_that_matches_no_purchase_order_line(): void
    {
        $input = MatchInputBuilder::make()
            ->withLine(quantityOrdered: 100, unitPriceOrdered: 10, quantityReceived: 100, quantityInvoiced: 100, unitPriceInvoiced: 10)
            ->withOrphanLine(quantity: 1, unitPrice: 2500)
            ->build();

        $outcome = $this->engine()->evaluate($input);

        $this->assertSame(MatchStatus::Exception, $outcome->status);
        // La ligne légitime reste payable : signaler un ajout suspect ne doit
        // pas geler le reste de la créance.
        $this->assertSame(1000.0, $outcome->matchedAmount);
        $this->assertSame(DiscrepancyType::MissingPurchaseOrderLine, $outcome->allDiscrepancies()[0]->type);
        $this->assertSame(MatchStatus::Exception, $outcome->lineOutcomes[1]->status);
    }

    // --- Règle n°5 : traçabilité ------------------------------------------

    #[Test]
    public function it_records_the_figures_the_decision_was_based_on(): void
    {
        $input = MatchInputBuilder::make()
            ->withLine(
                quantityOrdered: 100,
                unitPriceOrdered: 10,
                quantityReceived: 60,
                quantityInvoiced: 80,
                unitPriceInvoiced: 10,
                quantityAlreadyMatched: 20,
            )
            ->build();

        $evidence = $this->engine()->evaluate($input)->lineOutcomes[0]->evidence;

        $this->assertSame(100.0, $evidence['quantity_ordered']);
        $this->assertSame(60.0, $evidence['quantity_received']);
        $this->assertSame(20.0, $evidence['quantity_already_matched']);
        $this->assertSame(40.0, $evidence['quantity_available_for_matching']);
        $this->assertSame(80.0, $evidence['quantity_remaining_on_order']);
        $this->assertTrue($evidence['price_within_tolerance']);
    }

    #[Test]
    public function it_reports_the_engine_version_and_the_tolerance_it_applied(): void
    {
        $outcome = $this->engine(priceRatio: 0.02, priceAbsolute: 1.0)->evaluate(
            MatchInputBuilder::make()
                ->withLine(quantityOrdered: 10, unitPriceOrdered: 10, quantityReceived: 10, quantityInvoiced: 10, unitPriceInvoiced: 10)
                ->build(),
        );

        $this->assertSame('1.0.0-test', $outcome->engineVersion);
        $this->assertSame(
            [
                'price_ratio' => 0.02,
                'price_absolute' => 1.0,
                'quantity_ratio' => 0.0,
                'quantity_absolute' => 0.0,
                // La devise fait partie de la tolerance archivee : un seuil
                // absolu sans devise n'est pas interpretable.
                'currency' => 'EUR',
            ],
            $outcome->tolerance->toArray(),
        );
    }

    // --- Multi-lignes ------------------------------------------------------

    #[Test]
    public function it_aggregates_a_mixed_invoice_line_by_line(): void
    {
        $input = MatchInputBuilder::make()
            // Ligne 1 : conforme.
            ->withLine(quantityOrdered: 100, unitPriceOrdered: 10, quantityReceived: 100, quantityInvoiced: 100, unitPriceInvoiced: 10)
            // Ligne 2 : livraison partielle.
            ->withLine(quantityOrdered: 50, unitPriceOrdered: 20, quantityReceived: 20, quantityInvoiced: 50, unitPriceInvoiced: 20)
            // Ligne 3 : prix hors tolérance.
            ->withLine(quantityOrdered: 10, unitPriceOrdered: 100, quantityReceived: 10, quantityInvoiced: 10, unitPriceInvoiced: 130)
            ->build();

        $outcome = $this->engine()->evaluate($input);

        $this->assertSame(MatchStatus::Exception, $outcome->status);
        $this->assertSame(MatchStatus::Matched, $outcome->lineOutcomes[0]->status);
        $this->assertSame(MatchStatus::PartiallyMatched, $outcome->lineOutcomes[1]->status);
        $this->assertSame(MatchStatus::Exception, $outcome->lineOutcomes[2]->status);

        // 1000 (ligne 1) + 400 (20 x 20 sur la ligne 2) + 0 (ligne 3).
        $this->assertSame(1400.0, $outcome->matchedAmount);
        $this->assertSame(1000.0 + 1000.0 + 1300.0, $outcome->invoicedAmount);
    }

    #[Test]
    public function it_handles_fractional_quantities_without_rounding_drift(): void
    {
        // Le BTP facture au m3 et à la tonne : les quantités décimales sont la
        // norme, pas l'exception.
        $input = MatchInputBuilder::make()
            ->withLine(quantityOrdered: 12.5, unitPriceOrdered: 84.75, quantityReceived: 7.25, quantityInvoiced: 12.5, unitPriceInvoiced: 84.75)
            ->build();

        $outcome = $this->engine()->evaluate($input);

        $this->assertSame(MatchStatus::PartiallyMatched, $outcome->status);
        $this->assertSame(614.44, $outcome->matchedAmount);
        $this->assertSame(7.25, $outcome->lineOutcomes[0]->quantityMatched);
        $this->assertSame(5.25, $outcome->lineOutcomes[0]->quantityUnmatched);
    }
}
