<?php

namespace Tests\Feature\Matching;

use App\Domains\Invoicing\Enums\InvoiceStatus;
use App\Domains\Matching\Enums\ActorType;
use App\Domains\Matching\Enums\DiscrepancyType;
use App\Domains\Matching\Enums\MatchStatus;
use App\Domains\Matching\Enums\ReviewStatus;
use App\Domains\Payments\Enums\PaymentAuthorizationStatus;
use App\Models\MatchException;
use App\Models\PaymentAuthorization;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Concerns\BuildsProcurementScenario;
use Tests\TestCase;

/**
 * Revue humaine des écarts — règle fonctionnelle n°6.
 */
class MatchExceptionReviewTest extends TestCase
{
    use BuildsProcurementScenario, RefreshDatabase;

    /** Crée une facture bloquée par un écart de prix et rend l'écart ouvert. */
    private function givenPriceVarianceException(): MatchException
    {
        $this->actingAsRole('accountant');
        $this->givenPurchaseOrder([['quantity' => 100, 'price' => 10]]);
        $this->givenAcceptedDelivery([100]);

        $this->postJson('/api/invoices', $this->invoicePayload([
            ['line' => 0, 'quantity' => 100, 'price' => 12],
        ]))->assertCreated();

        return MatchException::query()->where('type', DiscrepancyType::PriceVariance)->firstOrFail();
    }

    #[Test]
    public function approving_a_price_variance_replays_the_matching_and_unlocks_the_payment(): void
    {
        $exception = $this->givenPriceVarianceException();

        $controller = $this->actingAsRole('controller');

        $this->postJson("/api/match-exceptions/{$exception->id}/review", [
            'decision' => ReviewStatus::Approved->value,
            'note' => 'Hausse contractuelle du ciment validée par le service achats.',
        ])
            ->assertOk()
            ->assertJsonPath('data.exception.review_status', ReviewStatus::Approved->value)
            ->assertJsonPath('data.exception.reviewed_by.id', $controller->id)
            // Le rapprochement rejoué porte le nom du relecteur : c'est lui qui
            // a déclenché la décision, pas le moteur seul.
            ->assertJsonPath('data.match_run.decided_by.actor_type', ActorType::User->value)
            ->assertJsonPath('data.match_run.decided_by.actor_id', $controller->id)
            ->assertJsonPath('data.match_run.trigger', 'exception_reviewed')
            ->assertJsonPath('data.match_run.status', MatchStatus::Matched->value)
            // Payé au prix facturé, que le relecteur a explicitement accepté.
            ->assertJsonPath('data.match_run.matched_amount', 1200);

        $this->assertDatabaseHas('payment_authorizations', [
            'invoice_id' => $exception->invoice_id,
            'amount' => 1200,
            'status' => PaymentAuthorizationStatus::Active->value,
        ]);
    }

    #[Test]
    public function rejecting_an_exception_disputes_the_invoice_and_revokes_the_payment(): void
    {
        $exception = $this->givenPriceVarianceException();
        $this->actingAsRole('controller');

        $this->postJson("/api/match-exceptions/{$exception->id}/review", [
            'decision' => ReviewStatus::Rejected->value,
            'note' => 'Prix non conforme au marché : avoir demandé au fournisseur.',
        ])
            ->assertOk()
            ->assertJsonPath('data.exception.review_status', ReviewStatus::Rejected->value)
            // Un refus ne relance pas le moteur : il clôt le circuit automatique.
            ->assertJsonPath('data.match_run', null);

        $this->assertDatabaseHas('invoices', [
            'id' => $exception->invoice_id,
            'status' => InvoiceStatus::Disputed->value,
        ]);

        $this->assertNull(PaymentAuthorization::query()->where('invoice_id', $exception->invoice_id)->active()->first());
    }

    #[Test]
    public function a_disputed_invoice_is_not_rematched_automatically(): void
    {
        $exception = $this->givenPriceVarianceException();
        $this->actingAsRole('controller');

        $this->postJson("/api/match-exceptions/{$exception->id}/review", [
            'decision' => ReviewStatus::Rejected->value,
            'note' => 'Facture erronée.',
        ])->assertOk();

        $this->postJson("/api/invoices/{$exception->invoice_id}/match-runs")
            ->assertStatus(409)
            ->assertJsonPath('error_code', 'invoice_disputed');
    }

    #[Test]
    public function an_exception_cannot_be_reviewed_twice(): void
    {
        $exception = $this->givenPriceVarianceException();
        $this->actingAsRole('controller');

        $payload = ['decision' => ReviewStatus::Approved->value, 'note' => 'Validé.'];

        $this->postJson("/api/match-exceptions/{$exception->id}/review", $payload)->assertOk();
        $this->postJson("/api/match-exceptions/{$exception->id}/review", $payload)
            ->assertStatus(409)
            ->assertJsonPath('error_code', 'exception_already_reviewed');
    }

    #[Test]
    public function a_review_requires_a_written_reason(): void
    {
        $exception = $this->givenPriceVarianceException();
        $this->actingAsRole('controller');

        $this->postJson("/api/match-exceptions/{$exception->id}/review", [
            'decision' => ReviewStatus::Approved->value,
        ])
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'validation_failed')
            ->assertJsonValidationErrors('note');
    }

    #[Test]
    public function approving_a_supplier_mismatch_never_unlocks_the_payment(): void
    {
        // Un fournisseur non concordant n'est pas dérogeable : même approuvé,
        // l'écart doit continuer à bloquer, sinon le contrôle se contourne
        // d'un clic.
        $this->actingAsRole('accountant');
        $this->givenPurchaseOrder([['quantity' => 100, 'price' => 10]]);
        $this->givenAcceptedDelivery([100]);

        $invoiceId = $this->postJson('/api/invoices', $this->invoicePayload([
            ['line' => 0, 'quantity' => 100, 'price' => 10],
        ]))->assertCreated()->json('data.id');

        // Le fournisseur du PO change après coup (correction de référentiel).
        $this->purchaseOrder->update(['supplier_id' => Supplier::factory()->create()->id]);

        $this->postJson("/api/invoices/{$invoiceId}/match-runs")->assertCreated();

        $exception = MatchException::query()->where('type', DiscrepancyType::SupplierMismatch)->firstOrFail();
        $this->assertFalse($exception->type->isOverridable());

        $this->actingAsRole('controller');
        $this->postJson("/api/match-exceptions/{$exception->id}/review", [
            'decision' => ReviewStatus::Approved->value,
            'note' => 'Tentative de déblocage.',
        ])
            ->assertOk()
            ->assertJsonPath('data.match_run.matched_amount', 0)
            ->assertJsonPath('data.match_run.status', MatchStatus::Exception->value);
    }

    #[Test]
    public function the_review_queue_can_be_filtered_and_is_paginated(): void
    {
        $this->givenPriceVarianceException();
        $this->actingAsRole('controller');

        $this->getJson('/api/match-exceptions?review_status=open')
            ->assertOk()
            ->assertJsonPath('data.0.type', DiscrepancyType::PriceVariance->value)
            ->assertJsonPath('data.0.is_overridable', true)
            ->assertJsonStructure(['data', 'links', 'meta' => ['current_page', 'last_page', 'per_page', 'total']]);

        $this->getJson('/api/match-exceptions?type='.DiscrepancyType::SupplierMismatch->value)
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }
}
