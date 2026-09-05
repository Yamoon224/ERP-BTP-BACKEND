<?php

namespace Tests\Feature\Payments;

use App\Domains\Payments\Enums\PaymentAuthorizationStatus;
use App\Models\Invoice;
use App\Models\PaymentAuthorization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Concerns\BuildsProcurementScenario;
use Tests\TestCase;

/**
 * Reglement d'une autorisation de paiement.
 *
 * Le point sensible : ce point d'entree ne doit jamais devenir un moyen de
 * payer ce que le moteur a bloque. Il ne prend aucun montant, refuse une
 * autorisation revoquee, et ne peut pas servir deux fois.
 */
class PaymentSettlementTest extends TestCase
{
    use BuildsProcurementScenario, RefreshDatabase;

    private function givenAuthorizedInvoice(): PaymentAuthorization
    {
        $this->actingAsRole('accountant');
        $this->givenPurchaseOrder([['quantity' => 100, 'price' => 10]]);
        $this->givenAcceptedDelivery([100]);

        $this->postJson('/api/invoices', $this->invoicePayload([
            ['line' => 0, 'quantity' => 100, 'price' => 10],
        ]))->assertCreated();

        return PaymentAuthorization::query()->sole();
    }

    #[Test]
    public function an_accountant_settles_an_active_authorization(): void
    {
        $authorization = $this->givenAuthorizedInvoice();

        $this->postJson("/api/payment-authorizations/{$authorization->id}/settle", [
            'payment_reference' => 'VIR-2026-00042',
            'payment_method' => 'transfer',
        ])
            ->assertOk()
            ->assertJsonPath('data.is_settled', true)
            ->assertJsonPath('data.payment_reference', 'VIR-2026-00042')
            // Le montant reste celui du rapprochement : le reglement ne le
            // renegocie pas.
            ->assertJsonPath('data.amount', 1000);

        $this->assertNotNull($authorization->refresh()->settled_at);
    }

    #[Test]
    public function the_settled_amount_cannot_be_chosen_by_the_caller(): void
    {
        $authorization = $this->givenAuthorizedInvoice();

        $this->postJson("/api/payment-authorizations/{$authorization->id}/settle", [
            'payment_reference' => 'VIR-2026-00043',
            'amount' => 999999,
        ])->assertOk()->assertJsonPath('data.amount', 1000);

        $this->assertSame('1000.00', $authorization->refresh()->amount);
    }

    #[Test]
    public function a_settlement_without_a_bank_reference_is_refused(): void
    {
        $authorization = $this->givenAuthorizedInvoice();

        $this->postJson("/api/payment-authorizations/{$authorization->id}/settle", [])
            ->assertStatus(422)
            ->assertJsonValidationErrors('payment_reference');
    }

    #[Test]
    public function an_authorization_cannot_be_settled_twice(): void
    {
        $authorization = $this->givenAuthorizedInvoice();

        $this->postJson("/api/payment-authorizations/{$authorization->id}/settle", [
            'payment_reference' => 'VIR-2026-00044',
        ])->assertOk();

        $this->postJson("/api/payment-authorizations/{$authorization->id}/settle", [
            'payment_reference' => 'VIR-2026-00045',
        ])
            ->assertStatus(409)
            ->assertJsonPath('error_code', 'payment_already_settled');
    }

    #[Test]
    public function a_revoked_authorization_cannot_be_settled(): void
    {
        $authorization = $this->givenAuthorizedInvoice();
        $invoice = Invoice::query()->sole();

        $this->postJson("/api/invoices/{$invoice->id}/cancel")->assertOk();

        $this->assertSame(PaymentAuthorizationStatus::Revoked, $authorization->refresh()->status);

        $this->postJson("/api/payment-authorizations/{$authorization->id}/settle", [
            'payment_reference' => 'VIR-2026-00046',
        ])
            ->assertStatus(409)
            ->assertJsonPath('error_code', 'payment_not_active');
    }

    #[Test]
    public function a_settled_authorization_survives_a_later_match_run(): void
    {
        $authorization = $this->givenAuthorizedInvoice();
        $invoice = Invoice::query()->sole();

        $this->postJson("/api/payment-authorizations/{$authorization->id}/settle", [
            'payment_reference' => 'VIR-2026-00047',
        ])->assertOk();

        $this->postJson("/api/invoices/{$invoice->id}/match-runs")->assertCreated();

        // Un virement parti ne se declasse pas : la nouvelle execution cree sa
        // propre autorisation, sans reecrire celle qui a justifie le paiement.
        $authorization->refresh();
        $this->assertSame(PaymentAuthorizationStatus::Active, $authorization->status);
        $this->assertNotNull($authorization->settled_at);
        $this->assertSame(2, PaymentAuthorization::query()->count());
    }

    #[Test]
    public function a_controller_may_read_but_not_settle(): void
    {
        $authorization = $this->givenAuthorizedInvoice();

        $this->actingAsRole('controller');

        $this->getJson('/api/payment-authorizations')->assertOk();

        $this->postJson("/api/payment-authorizations/{$authorization->id}/settle", [
            'payment_reference' => 'VIR-2026-00048',
        ])->assertStatus(403)->assertJsonPath('error_code', 'forbidden');
    }

    #[Test]
    public function the_listing_filters_on_settlement_state(): void
    {
        $authorization = $this->givenAuthorizedInvoice();

        $this->getJson('/api/payment-authorizations?settled=false')
            ->assertOk()
            ->assertJsonPath('meta.total', 1);

        $this->postJson("/api/payment-authorizations/{$authorization->id}/settle", [
            'payment_reference' => 'VIR-2026-00049',
        ])->assertOk();

        $this->getJson('/api/payment-authorizations?settled=false')
            ->assertOk()
            ->assertJsonPath('meta.total', 0);

        $this->getJson('/api/payment-authorizations?settled=true')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.payment_reference', 'VIR-2026-00049');
    }
}
