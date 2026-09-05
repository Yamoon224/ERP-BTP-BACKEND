<?php

namespace Tests\Feature\Matching;

use App\Domains\Invoicing\Enums\InvoiceStatus;
use App\Domains\Matching\Enums\DiscrepancyType;
use App\Domains\Matching\Enums\MatchStatus;
use App\Domains\Shared\Enums\Currency;
use App\Domains\Shared\Enums\ExchangeRateSource;
use App\Models\ExchangeRate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Concerns\BuildsProcurementScenario;
use Tests\TestCase;

/**
 * Parcours multidevise de bout en bout : API → conversion → moteur → base.
 */
class MultiCurrencyInvoiceTest extends TestCase
{
    use BuildsProcurementScenario, RefreshDatabase;

    private const XOF_PEG = 655.957;

    private function givenExchangeRates(): void
    {
        ExchangeRate::create([
            'base_currency' => Currency::EUR,
            'quote_currency' => Currency::XOF,
            'rate' => self::XOF_PEG,
            'source' => ExchangeRateSource::FixedPeg,
            'effective_from' => '1999-01-01',
        ]);

        ExchangeRate::create([
            'base_currency' => Currency::EUR,
            'quote_currency' => Currency::USD,
            'rate' => 1.0850,
            'source' => ExchangeRateSource::Manual,
            'effective_from' => '2020-01-01',
        ]);
    }

    #[Test]
    public function a_dollar_invoice_against_a_euro_purchase_order_is_matched_and_paid_in_dollars(): void
    {
        $this->actingAsRole('accountant');
        $this->givenExchangeRates();
        $this->givenPurchaseOrder([['quantity' => 10, 'price' => 100]]);  // 100 EUR l'unité
        $this->givenAcceptedDelivery([10]);

        $payload = $this->invoicePayload([['line' => 0, 'quantity' => 10, 'price' => 108.50]]);
        $payload['currency'] = 'USD';

        $response = $this->postJson('/api/invoices', $payload)->assertCreated();

        $response->assertJsonPath('data.status', InvoiceStatus::Approved->value)
            ->assertJsonPath('data.currency', 'USD')
            ->assertJsonPath('data.latest_match_run.status', MatchStatus::Matched->value)
            // Le fournisseur est réglé dans la devise où il a facturé.
            ->assertJsonPath('data.latest_match_run.currency', 'USD')
            ->assertJsonPath('data.latest_match_run.matched_amount', 1085)
            ->assertJsonPath('data.payment_authorization.amount', 1085)
            ->assertJsonPath('data.payment_authorization.currency', 'USD')
            // La contre-valeur sert au pilotage.
            ->assertJsonPath('data.payment_authorization.base_currency', 'EUR')
            ->assertJsonPath('data.payment_authorization.base_amount', 1000);
    }

    #[Test]
    public function the_applied_exchange_rate_is_archived_with_the_decision(): void
    {
        $this->actingAsRole('accountant');
        $this->givenExchangeRates();
        $this->givenPurchaseOrder([['quantity' => 10, 'price' => 100]]);
        $this->givenAcceptedDelivery([10]);

        $payload = $this->invoicePayload([['line' => 0, 'quantity' => 10, 'price' => 108.50]]);
        $payload['currency'] = 'USD';

        $invoiceId = $this->postJson('/api/invoices', $payload)->assertCreated()->json('data.id');

        $run = $this->getJson("/api/invoices/{$invoiceId}/match-runs")->assertOk()->json('data.0');

        $this->assertSame('USD', $run['exchange_rate_snapshot']['invoice_currency']);
        $this->assertSame('EUR', $run['exchange_rate_snapshot']['comparison_currency']);
        $this->assertNotNull($run['exchange_rate_snapshot']['invoice_to_comparison']['rate']);
        $this->assertSame(
            ExchangeRateSource::Manual->value,
            $run['exchange_rate_snapshot']['invoice_to_comparison']['source'],
        );
    }

    #[Test]
    public function an_overcharge_hidden_by_the_currency_is_still_detected(): void
    {
        $this->actingAsRole('accountant');
        $this->givenExchangeRates();
        $this->givenPurchaseOrder([['quantity' => 10, 'price' => 100]]);
        $this->givenAcceptedDelivery([10]);

        // 130 USD l'unité, soit ~119,82 EUR contre 100 EUR commandés : +20 %.
        // Sans conversion, « 130 » contre « 100 » serait déjà suspect, mais le
        // montant exact de l'écart n'aurait aucun sens.
        $payload = $this->invoicePayload([['line' => 0, 'quantity' => 10, 'price' => 130]]);
        $payload['currency'] = 'USD';

        $this->postJson('/api/invoices', $payload)
            ->assertCreated()
            ->assertJsonPath('data.status', InvoiceStatus::UnderReview->value)
            ->assertJsonPath('data.latest_match_run.matched_amount', 0);

        $this->assertDatabaseHas('match_exceptions', ['type' => DiscrepancyType::PriceVariance->value]);
    }

    #[Test]
    public function an_invoice_in_an_unquoted_currency_is_blocked_rather_than_guessed(): void
    {
        $this->actingAsRole('accountant');
        // Seule la parité XOF est connue : aucun taux pour le dollar.
        ExchangeRate::create([
            'base_currency' => Currency::EUR,
            'quote_currency' => Currency::XOF,
            'rate' => self::XOF_PEG,
            'source' => ExchangeRateSource::FixedPeg,
            'effective_from' => '1999-01-01',
        ]);

        $this->givenPurchaseOrder([['quantity' => 10, 'price' => 100]]);
        $this->givenAcceptedDelivery([10]);

        $payload = $this->invoicePayload([['line' => 0, 'quantity' => 10, 'price' => 108.50]]);
        $payload['currency'] = 'USD';

        $this->postJson('/api/invoices', $payload)
            ->assertCreated()
            ->assertJsonPath('data.status', InvoiceStatus::UnderReview->value)
            ->assertJsonPath('data.latest_match_run.matched_amount', 0)
            ->assertJsonPath('data.payment_authorization', null);

        $this->assertDatabaseHas('match_exceptions', [
            'type' => DiscrepancyType::MissingExchangeRate->value,
        ]);
    }

    #[Test]
    public function a_cfa_franc_invoice_is_matched_without_any_decimal(): void
    {
        $this->actingAsRole('accountant');
        $this->givenExchangeRates();
        $this->givenPurchaseOrder([['quantity' => 100, 'price' => 22000]], currency: 'XOF');
        $this->givenAcceptedDelivery([60]);

        $payload = $this->invoicePayload([['line' => 0, 'quantity' => 100, 'price' => 22000]]);
        $payload['currency'] = 'XOF';

        $response = $this->postJson('/api/invoices', $payload)->assertCreated();

        // 60 t reçues sur 100 facturées : 1 320 000 XOF payables, sans centime.
        $response->assertJsonPath('data.currency', 'XOF')
            ->assertJsonPath('data.latest_match_run.matched_amount', 1320000)
            ->assertJsonPath('data.payment_authorization.currency', 'XOF')
            ->assertJsonPath('data.payment_authorization.amount', 1320000);

        $baseAmount = $response->json('data.payment_authorization.base_amount');
        $this->assertEqualsWithDelta(1320000 / self::XOF_PEG, $baseAmount, 0.01);
    }

    #[Test]
    public function the_dashboard_aggregates_every_currency_into_the_reference_one(): void
    {
        $this->actingAsRole('accountant');
        $this->givenExchangeRates();

        // Une facture en euros…
        $this->givenPurchaseOrder([['quantity' => 10, 'price' => 100]]);
        $this->givenAcceptedDelivery([10]);
        $this->postJson('/api/invoices', $this->invoicePayload(
            [['line' => 0, 'quantity' => 10, 'price' => 100]],
            'FAC-EUR',
        ))->assertCreated();

        // …et une facture en dollars, sur un autre bon de commande.
        $this->givenPurchaseOrder([['quantity' => 10, 'price' => 100]]);
        $this->givenAcceptedDelivery([10], 'BL-USD');
        $usdPayload = $this->invoicePayload([['line' => 0, 'quantity' => 10, 'price' => 108.50]], 'FAC-USD');
        $usdPayload['currency'] = 'USD';
        $this->postJson('/api/invoices', $usdPayload)->assertCreated();

        $summary = $this->getJson('/api/dashboard/matching')->assertOk()->json('data');

        // 1 000 EUR + 1 085 USD (= 1 000 EUR) = 2 000 EUR.
        $this->assertSame('EUR', $summary['amounts']['currency']);
        $this->assertEqualsWithDelta(2000.0, $summary['amounts']['authorized_for_payment'], 0.01);

        // La ventilation conserve la devise de règlement réelle.
        $byCurrency = array_column($summary['amounts']['by_currency'], 'amount', 'currency');
        $this->assertEqualsWithDelta(1000.0, $byCurrency['EUR'], 0.01);
        $this->assertEqualsWithDelta(1085.0, $byCurrency['USD'], 0.01);
    }

    #[Test]
    public function it_rejects_an_unsupported_currency(): void
    {
        $this->actingAsRole('accountant');
        $this->givenPurchaseOrder();

        $payload = $this->invoicePayload([['line' => 0, 'quantity' => 1, 'price' => 10]]);
        $payload['currency'] = 'GBP';

        $this->postJson('/api/invoices', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors('currency');
    }
}
