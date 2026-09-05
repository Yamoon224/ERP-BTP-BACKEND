<?php

namespace Tests\Unit\Matching;

use App\Domains\Matching\DTOs\Tolerance;
use App\Domains\Matching\Engines\ThreeWayMatchingEngine;
use App\Domains\Matching\Enums\DiscrepancyType;
use App\Domains\Matching\Enums\MatchStatus;
use App\Domains\Matching\Policies\ConfiguredTolerancePolicy;
use App\Domains\Shared\Enums\Currency;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Rapprochement multidevise.
 *
 * Une facture peut être libellée en EUR, USD ou XOF, indépendamment de la
 * devise de son bon de commande. Les prix sont alors confrontés dans la devise
 * du PO — la référence contractuelle — tandis que les montants payables restent
 * dans la devise de la facture, celle du règlement.
 *
 * La parité du franc CFA utilisée ici (1 EUR = 655,957 XOF) est la parité fixe
 * réglementaire, pas un taux de marché.
 */
class MultiCurrencyMatchingTest extends TestCase
{
    private const XOF_PEG = 655.957;

    private function engine(
        float $priceRatio = 0.01,
        float $priceAbsolute = 0.5,
        float $quantityRatio = 0.0,
    ): ThreeWayMatchingEngine {
        return new ThreeWayMatchingEngine(
            new ConfiguredTolerancePolicy(
                new Tolerance($priceRatio, $priceAbsolute, $quantityRatio, 0.0, Currency::EUR),
            ),
            '1.0.0-test',
        );
    }

    // --- Conversion nominale ----------------------------------------------

    #[Test]
    public function it_matches_a_dollar_invoice_against_a_euro_purchase_order(): void
    {
        // PO : 100 u à 10 EUR. Facture : 100 u à 10,85 USD, taux 1 USD = 0,9217 EUR
        // → 10,0004 EUR l'unité, soit un écart de 4 dix-millièmes.
        $input = MatchInputBuilder::make()
            ->withInvoiceCurrency(Currency::USD, rateToComparison: 0.9217, rateToBase: 0.9217)
            ->withLine(quantityOrdered: 100, unitPriceOrdered: 10, quantityReceived: 100, quantityInvoiced: 100, unitPriceInvoiced: 10.85)
            ->build();

        $outcome = $this->engine()->evaluate($input);

        $this->assertSame(MatchStatus::Matched, $outcome->status);
        // Le montant payable reste en dollars : c'est ce que le fournisseur
        // recevra.
        $this->assertSame(Currency::USD, $outcome->currency);
        $this->assertSame(1085.0, $outcome->matchedAmount);
        // La contre-valeur en devise de référence sert au pilotage.
        $this->assertSame(Currency::EUR, $outcome->baseCurrency);
        $this->assertSame(1000.04, $outcome->baseMatchedAmount);
    }

    #[Test]
    public function it_matches_a_cfa_franc_invoice_against_a_euro_purchase_order(): void
    {
        // 540 EUR au PO, facturé 354 216,78 XOF (= 540 × 655,957).
        $input = MatchInputBuilder::make()
            ->withInvoiceCurrency(Currency::XOF, rateToComparison: 1 / self::XOF_PEG)
            ->withLine(quantityOrdered: 15, unitPriceOrdered: 540, quantityReceived: 15, quantityInvoiced: 15, unitPriceInvoiced: 354216.78)
            ->build();

        $outcome = $this->engine()->evaluate($input);

        $this->assertSame(MatchStatus::Matched, $outcome->status);
        $this->assertSame(Currency::XOF, $outcome->currency);
        // Le franc CFA n'a pas de centime : le montant est un entier.
        $this->assertSame(5313252.0, $outcome->matchedAmount);
        $this->assertSame(0.0, fmod($outcome->matchedAmount, 1.0), 'Un montant en XOF ne doit jamais porter de décimale.');
    }

    #[Test]
    public function it_matches_a_euro_invoice_against_a_cfa_franc_purchase_order(): void
    {
        // Sens inverse : le PO est en XOF, la facture en EUR.
        $input = MatchInputBuilder::make()
            ->withPurchaseOrderCurrency(Currency::XOF, baseToComparisonRate: self::XOF_PEG)
            ->withInvoiceCurrency(Currency::EUR, rateToComparison: self::XOF_PEG, rateToBase: 1.0)
            ->withLine(quantityOrdered: 10, unitPriceOrdered: 65595.7, quantityReceived: 10, quantityInvoiced: 10, unitPriceInvoiced: 100)
            ->build();

        $outcome = $this->engine()->evaluate($input);

        $this->assertSame(MatchStatus::Matched, $outcome->status);
        $this->assertSame(Currency::EUR, $outcome->currency);
        $this->assertSame(1000.0, $outcome->matchedAmount);
    }

    // --- Écarts détectés à travers la conversion ---------------------------

    #[Test]
    public function it_flags_an_overcharge_that_only_appears_after_conversion(): void
    {
        // 12 USD l'unité contre 10 EUR au PO : converti à 0,9217, cela fait
        // 11,06 EUR — soit +10,6 %, très au-delà de la tolérance de 1 %.
        // C'est exactement le cas qu'une comparaison sans conversion raterait.
        $input = MatchInputBuilder::make()
            ->withInvoiceCurrency(Currency::USD, rateToComparison: 0.9217)
            ->withLine(quantityOrdered: 100, unitPriceOrdered: 10, quantityReceived: 100, quantityInvoiced: 100, unitPriceInvoiced: 12)
            ->build();

        $outcome = $this->engine()->evaluate($input);

        $this->assertSame(MatchStatus::Exception, $outcome->status);
        $this->assertSame(0.0, $outcome->matchedAmount);
        $this->assertSame(DiscrepancyType::PriceVariance, $outcome->allDiscrepancies()[0]->type);
    }

    #[Test]
    public function a_price_variance_message_states_both_currencies_and_the_rate(): void
    {
        // Un relecteur ne compare pas « 12 USD » et « 10 EUR » de tête : le
        // message doit lui donner le montant converti et le taux appliqué.
        $input = MatchInputBuilder::make()
            ->withInvoiceCurrency(Currency::USD, rateToComparison: 0.9217)
            ->withLine(quantityOrdered: 100, unitPriceOrdered: 10, quantityReceived: 100, quantityInvoiced: 100, unitPriceInvoiced: 12)
            ->build();

        $message = $this->engine()->evaluate($input)->allDiscrepancies()[0]->message;

        $this->assertStringContainsString('USD', $message);
        $this->assertStringContainsString('EUR', $message);
        $this->assertStringContainsString('taux', $message);
    }

    #[Test]
    public function it_absorbs_a_rounding_difference_introduced_by_the_conversion(): void
    {
        // 10,8500 USD à 0,92165437 donne 10,00 EUR à un dix-millième près.
        // Une conversion ne doit pas fabriquer un écart de prix.
        $input = MatchInputBuilder::make()
            ->withInvoiceCurrency(Currency::USD, rateToComparison: 0.92165437)
            ->withLine(quantityOrdered: 100, unitPriceOrdered: 10, quantityReceived: 100, quantityInvoiced: 100, unitPriceInvoiced: 10.85)
            ->build();

        $outcome = $this->engine()->evaluate($input)->allDiscrepancies();

        $this->assertSame([], $outcome, 'Un écart d\'arrondi de conversion ne doit pas partir en revue.');
    }

    // --- Taux manquant ------------------------------------------------------

    #[Test]
    public function it_blocks_the_invoice_when_no_exchange_rate_is_available(): void
    {
        // Devises différentes et aucun taux : le système ne devine pas, il
        // bloque et le signale.
        $input = MatchInputBuilder::make()
            ->withInvoiceCurrency(Currency::USD, rateToComparison: null)
            ->withLine(quantityOrdered: 100, unitPriceOrdered: 10, quantityReceived: 100, quantityInvoiced: 100, unitPriceInvoiced: 10)
            ->build();

        $outcome = $this->engine()->evaluate($input);

        $this->assertSame(MatchStatus::Exception, $outcome->status);
        $this->assertSame(0.0, $outcome->matchedAmount);
        $this->assertSame(DiscrepancyType::MissingExchangeRate, $outcome->invoiceDiscrepancies[0]->type);
    }

    #[Test]
    public function a_missing_exchange_rate_can_never_be_waived_by_a_reviewer(): void
    {
        // Déroger à un taux manquant reviendrait à autoriser un montant qu'on
        // ne sait pas calculer.
        $this->assertFalse(DiscrepancyType::MissingExchangeRate->isOverridable());
    }

    // --- Tolérance et devises ----------------------------------------------

    #[Test]
    public function it_converts_the_absolute_price_tolerance_into_the_comparison_currency(): void
    {
        // Tolérance absolue de 0,50 EUR, PO en XOF : appliquée telle quelle,
        // elle vaudrait un demi-franc — soit moins que la plus petite unité
        // existante. Convertie, elle vaut environ 328 XOF.
        $input = MatchInputBuilder::make()
            ->withPurchaseOrderCurrency(Currency::XOF, baseToComparisonRate: self::XOF_PEG)
            ->withInvoiceCurrency(Currency::XOF, rateToComparison: 1.0, rateToBase: 1 / self::XOF_PEG)
            // 200 XOF d'écart sur un prix de 1 000 XOF : 20 % en relatif, mais
            // sous le seuil absolu converti (≈ 328 XOF).
            ->withLine(quantityOrdered: 10, unitPriceOrdered: 1000, quantityReceived: 10, quantityInvoiced: 10, unitPriceInvoiced: 1200)
            ->build();

        $outcome = $this->engine()->evaluate($input);

        $this->assertSame([], $outcome->allDiscrepancies());
        $this->assertSame(MatchStatus::Matched, $outcome->status);
    }

    #[Test]
    public function the_archived_tolerance_states_the_currency_it_was_applied_in(): void
    {
        $input = MatchInputBuilder::make()
            ->withPurchaseOrderCurrency(Currency::XOF, baseToComparisonRate: self::XOF_PEG)
            ->withInvoiceCurrency(Currency::XOF, rateToComparison: 1.0, rateToBase: 1 / self::XOF_PEG)
            ->withLine(quantityOrdered: 10, unitPriceOrdered: 1000, quantityReceived: 10, quantityInvoiced: 10, unitPriceInvoiced: 1000)
            ->build();

        $snapshot = $this->engine()->evaluate($input)->tolerance->toArray();

        $this->assertSame(Currency::XOF->value, $snapshot['currency']);
        // 0,50 EUR converti à la parité fixe.
        $this->assertEqualsWithDelta(0.5 * self::XOF_PEG, $snapshot['price_absolute'], 0.001);
    }

    // --- Traçabilité --------------------------------------------------------

    #[Test]
    public function it_archives_the_exchange_rates_it_applied(): void
    {
        // Un montant converti sans son taux est invérifiable : la décision doit
        // embarquer de quoi la rejouer.
        $input = MatchInputBuilder::make()
            ->withInvoiceCurrency(Currency::USD, rateToComparison: 0.9217, rateToBase: 0.9217)
            ->withLine(quantityOrdered: 100, unitPriceOrdered: 10, quantityReceived: 100, quantityInvoiced: 100, unitPriceInvoiced: 10.85)
            ->build();

        $snapshot = $this->engine()->evaluate($input)->exchangeRateSnapshot;

        $this->assertSame('USD', $snapshot['invoice_currency']);
        $this->assertSame('EUR', $snapshot['comparison_currency']);
        $this->assertSame('EUR', $snapshot['base_currency']);
        $this->assertSame(0.9217, $snapshot['invoice_to_comparison']['rate']);
        $this->assertSame('2026-01-01', $snapshot['invoice_to_comparison']['effective_from']);
    }

    #[Test]
    public function the_evidence_shows_the_converted_price_alongside_the_invoiced_one(): void
    {
        $input = MatchInputBuilder::make()
            ->withInvoiceCurrency(Currency::USD, rateToComparison: 0.9217)
            ->withLine(quantityOrdered: 100, unitPriceOrdered: 10, quantityReceived: 100, quantityInvoiced: 100, unitPriceInvoiced: 10.85)
            ->build();

        $evidence = $this->engine()->evaluate($input)->lineOutcomes[0]->evidence;

        $this->assertTrue($evidence['conversion_applied']);
        $this->assertSame(10.85, $evidence['unit_price_invoiced']);
        $this->assertSame(10.000445, $evidence['unit_price_invoiced_in_comparison_currency']);
        $this->assertSame(10.0, $evidence['unit_price_ordered']);
        $this->assertSame(0.9217, $evidence['exchange_rate']);
    }

    #[Test]
    public function it_reports_no_conversion_when_both_documents_share_a_currency(): void
    {
        $input = MatchInputBuilder::make()
            ->withLine(quantityOrdered: 10, unitPriceOrdered: 10, quantityReceived: 10, quantityInvoiced: 10, unitPriceInvoiced: 10)
            ->build();

        $evidence = $this->engine()->evaluate($input)->lineOutcomes[0]->evidence;

        $this->assertFalse($evidence['conversion_applied']);
    }

    // --- Interaction avec les autres règles ---------------------------------

    #[Test]
    public function a_partial_delivery_is_still_capped_correctly_across_currencies(): void
    {
        // La conversion ne doit pas perturber le plafonnement par les
        // quantités : 40 reçus sur 100 facturés, quelle que soit la devise.
        $input = MatchInputBuilder::make()
            ->withInvoiceCurrency(Currency::USD, rateToComparison: 0.9217, rateToBase: 0.9217)
            ->withLine(quantityOrdered: 100, unitPriceOrdered: 10, quantityReceived: 40, quantityInvoiced: 100, unitPriceInvoiced: 10.85)
            ->build();

        $outcome = $this->engine()->evaluate($input);

        $this->assertSame(MatchStatus::PartiallyMatched, $outcome->status);
        $this->assertSame(434.0, $outcome->matchedAmount);
        $this->assertSame(651.0, $outcome->unmatchedAmount);
    }
}
