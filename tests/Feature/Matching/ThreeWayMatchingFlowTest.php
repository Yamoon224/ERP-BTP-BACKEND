<?php

namespace Tests\Feature\Matching;

use App\Domains\Invoicing\Enums\InvoiceStatus;
use App\Domains\Matching\Enums\ActorType;
use App\Domains\Matching\Enums\DiscrepancyType;
use App\Domains\Matching\Enums\MatchStatus;
use App\Domains\Matching\Enums\ReviewStatus;
use App\Domains\Payments\Enums\PaymentAuthorizationStatus;
use App\Domains\Receiving\Enums\DeliveryNoteStatus;
use App\Models\PaymentAuthorization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Concerns\BuildsProcurementScenario;
use Tests\TestCase;

/**
 * Tests d'intégration du parcours complet : API → validation → service →
 * moteur → dépôt → base. Ils vérifient que les couches se parlent
 * correctement, là où les tests unitaires vérifient les règles elles-mêmes.
 */
class ThreeWayMatchingFlowTest extends TestCase
{
    use BuildsProcurementScenario, RefreshDatabase;

    #[Test]
    public function submitting_an_invoice_immediately_matches_it_and_authorises_the_payment(): void
    {
        $this->actingAsRole('accountant');
        $this->givenPurchaseOrder([['quantity' => 100, 'price' => 10]]);
        $this->givenAcceptedDelivery([100]);

        $response = $this->postJson('/api/invoices', $this->invoicePayload([
            ['line' => 0, 'quantity' => 100, 'price' => 10],
        ]));

        $response->assertCreated()
            ->assertJsonPath('data.status', InvoiceStatus::Approved->value)
            ->assertJsonPath('data.total_amount', 1000)
            ->assertJsonPath('data.latest_match_run.status', MatchStatus::Matched->value)
            ->assertJsonPath('data.latest_match_run.matched_amount', 1000)
            ->assertJsonPath('data.latest_match_run.unmatched_amount', 0)
            ->assertJsonPath('data.payment_authorization.amount', 1000);

        $this->assertDatabaseHas('payment_authorizations', [
            'amount' => 1000,
            'status' => PaymentAuthorizationStatus::Active->value,
        ]);
    }

    #[Test]
    public function it_authorises_only_the_delivered_portion_of_an_invoice(): void
    {
        $this->actingAsRole('accountant');
        $this->givenPurchaseOrder([['quantity' => 100, 'price' => 10]]);
        $this->givenAcceptedDelivery([40]);

        $this->postJson('/api/invoices', $this->invoicePayload([
            ['line' => 0, 'quantity' => 100, 'price' => 10],
        ]))
            ->assertCreated()
            ->assertJsonPath('data.status', InvoiceStatus::PartiallyApproved->value)
            ->assertJsonPath('data.latest_match_run.matched_amount', 400)
            ->assertJsonPath('data.latest_match_run.unmatched_amount', 600)
            ->assertJsonPath('data.payment_authorization.amount', 400);
    }

    #[Test]
    public function a_draft_delivery_note_grants_no_right_to_payment(): void
    {
        $this->actingAsRole('accountant');
        $this->givenPurchaseOrder([['quantity' => 100, 'price' => 10]]);
        // Marchandise saisie mais non contrôlée : elle ne compte pas.
        $this->givenDelivery([100], DeliveryNoteStatus::Draft);

        $this->postJson('/api/invoices', $this->invoicePayload([
            ['line' => 0, 'quantity' => 100, 'price' => 10],
        ]))
            ->assertCreated()
            ->assertJsonPath('data.latest_match_run.matched_amount', 0)
            ->assertJsonPath('data.payment_authorization', null);
    }

    #[Test]
    public function a_rejected_delivery_note_grants_no_right_to_payment(): void
    {
        $this->actingAsRole('accountant');
        $this->givenPurchaseOrder([['quantity' => 100, 'price' => 10]]);
        $this->givenDelivery([100], DeliveryNoteStatus::Rejected);

        $this->postJson('/api/invoices', $this->invoicePayload([
            ['line' => 0, 'quantity' => 100, 'price' => 10],
        ]))
            ->assertCreated()
            ->assertJsonPath('data.latest_match_run.matched_amount', 0);
    }

    #[Test]
    public function two_invoices_cannot_be_paid_for_the_same_delivery(): void
    {
        $this->actingAsRole('accountant');
        $this->givenPurchaseOrder([['quantity' => 100, 'price' => 10]]);
        $this->givenAcceptedDelivery([100]);

        // Première facture : consomme les 100 unités reçues.
        $this->postJson('/api/invoices', $this->invoicePayload(
            [['line' => 0, 'quantity' => 100, 'price' => 10]],
            'FAC-A',
        ))->assertCreated()->assertJsonPath('data.payment_authorization.amount', 1000);

        // Seconde facture, même marchandise : plus rien à payer.
        $this->postJson('/api/invoices', $this->invoicePayload(
            [['line' => 0, 'quantity' => 100, 'price' => 10]],
            'FAC-B',
        ))
            ->assertCreated()
            ->assertJsonPath('data.latest_match_run.matched_amount', 0)
            ->assertJsonPath('data.payment_authorization', null);

        $this->assertSame(
            1000.0,
            (float) PaymentAuthorization::query()->where('status', PaymentAuthorizationStatus::Active)->sum('amount'),
            'Une même livraison ne doit ouvrir droit au paiement quune seule fois.',
        );
    }

    #[Test]
    public function the_same_invoice_reference_cannot_be_submitted_twice(): void
    {
        $this->actingAsRole('accountant');
        $this->givenPurchaseOrder();
        $this->givenAcceptedDelivery([100]);

        $payload = $this->invoicePayload([['line' => 0, 'quantity' => 10, 'price' => 10]], 'FAC-DUP');

        $this->postJson('/api/invoices', $payload)->assertCreated();
        $this->postJson('/api/invoices', $payload)
            ->assertStatus(409)
            ->assertJsonPath('error_code', 'duplicate_invoice');
    }

    #[Test]
    public function accepting_a_delivery_note_unblocks_the_invoices_waiting_for_it(): void
    {
        $accountant = $this->actingAsRole('accountant');
        $this->givenPurchaseOrder([['quantity' => 100, 'price' => 10]]);

        // Facture reçue avant la marchandise : rien n'est payable.
        $invoiceId = $this->postJson('/api/invoices', $this->invoicePayload([
            ['line' => 0, 'quantity' => 100, 'price' => 10],
        ]))->assertCreated()->json('data.id');

        $this->assertNull(
            PaymentAuthorization::query()->where('invoice_id', $invoiceId)->active()->first(),
        );

        // La marchandise arrive et le magasinier la valide.
        $this->actingAsRole('warehouse');
        $deliveryNote = $this->postJson('/api/delivery-notes', [
            'reference' => 'BL-LATE',
            'purchase_order_id' => $this->purchaseOrder->id,
            'received_at' => now()->toDateString(),
            'lines' => [[
                'purchase_order_line_id' => $this->purchaseOrderLines[0]->id,
                'quantity_received' => 100,
            ]],
        ])->assertCreated()->json('data.id');

        $this->postJson("/api/delivery-notes/{$deliveryNote}/review", ['status' => 'accepted'])->assertOk();

        // Le rapprochement a été rejoué automatiquement : la facture est payable.
        $this->actingAs($accountant)
            ->getJson("/api/invoices/{$invoiceId}")
            ->assertOk()
            ->assertJsonPath('data.status', InvoiceStatus::Approved->value)
            ->assertJsonPath('data.payment_authorization.amount', 1000);
    }

    #[Test]
    public function cancelling_an_invoice_revokes_its_payment_authorisation_and_frees_the_quantities(): void
    {
        $this->actingAsRole('accountant');
        $this->givenPurchaseOrder([['quantity' => 100, 'price' => 10]]);
        $this->givenAcceptedDelivery([100]);

        $invoiceId = $this->postJson('/api/invoices', $this->invoicePayload(
            [['line' => 0, 'quantity' => 100, 'price' => 10]],
            'FAC-CANCEL',
        ))->assertCreated()->json('data.id');

        $this->postJson("/api/invoices/{$invoiceId}/cancel")
            ->assertOk()
            ->assertJsonPath('data.status', InvoiceStatus::Cancelled->value);

        $this->assertDatabaseHas('payment_authorizations', [
            'invoice_id' => $invoiceId,
            'status' => PaymentAuthorizationStatus::Revoked->value,
        ]);

        // La quantité libérée redevient payable pour une facture corrigée.
        $this->postJson('/api/invoices', $this->invoicePayload(
            [['line' => 0, 'quantity' => 100, 'price' => 10]],
            'FAC-REPLACEMENT',
        ))
            ->assertCreated()
            ->assertJsonPath('data.payment_authorization.amount', 1000);
    }

    #[Test]
    public function a_price_variance_beyond_tolerance_creates_a_reviewable_exception(): void
    {
        $this->actingAsRole('accountant');
        $this->givenPurchaseOrder([['quantity' => 100, 'price' => 10]]);
        $this->givenAcceptedDelivery([100]);

        $this->postJson('/api/invoices', $this->invoicePayload([
            ['line' => 0, 'quantity' => 100, 'price' => 12],
        ]))
            ->assertCreated()
            ->assertJsonPath('data.status', InvoiceStatus::UnderReview->value)
            ->assertJsonPath('data.latest_match_run.matched_amount', 0);

        $this->assertDatabaseHas('match_exceptions', [
            'type' => DiscrepancyType::PriceVariance->value,
            'review_status' => ReviewStatus::Open->value,
        ]);
    }

    #[Test]
    public function an_invoice_line_without_a_purchase_order_line_is_accepted_then_flagged(): void
    {
        $this->actingAsRole('accountant');
        $this->givenPurchaseOrder([['quantity' => 100, 'price' => 10]]);
        $this->givenAcceptedDelivery([100]);

        $payload = $this->invoicePayload([['line' => 0, 'quantity' => 100, 'price' => 10]]);
        $payload['lines'][] = [
            'purchase_order_line_id' => null,
            'description' => 'Frais de dossier non commandés',
            'quantity' => 1,
            'unit_price' => 2500,
        ];

        $this->postJson('/api/invoices', $payload)
            ->assertCreated()
            // La ligne légitime reste payable, la ligne ajoutée est signalée.
            ->assertJsonPath('data.latest_match_run.matched_amount', 1000)
            ->assertJsonPath('data.status', InvoiceStatus::UnderReview->value);

        $this->assertDatabaseHas('match_exceptions', [
            'type' => DiscrepancyType::MissingPurchaseOrderLine->value,
        ]);
    }

    #[Test]
    public function an_invoice_line_pointing_at_another_purchase_order_is_rejected(): void
    {
        $this->actingAsRole('accountant');
        $this->givenPurchaseOrder([['quantity' => 100, 'price' => 10]]);
        $foreignLine = $this->purchaseOrderLines[0];

        // Nouveau PO, et une facture qui prétend porter sur une ligne du premier.
        $this->givenPurchaseOrder([['quantity' => 50, 'price' => 20]]);

        $payload = $this->invoicePayload([['line' => 0, 'quantity' => 10, 'price' => 20]]);
        $payload['lines'][0]['purchase_order_line_id'] = $foreignLine->id;

        $this->postJson('/api/invoices', $payload)
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'invoice_line_not_on_purchase_order');
    }

    #[Test]
    public function every_match_run_records_who_decided_when_and_on_what_basis(): void
    {
        $accountant = $this->actingAsRole('accountant');
        $this->givenPurchaseOrder([['quantity' => 100, 'price' => 10]]);
        $this->givenAcceptedDelivery([60]);

        $invoiceId = $this->postJson('/api/invoices', $this->invoicePayload([
            ['line' => 0, 'quantity' => 100, 'price' => 10],
        ]))->assertCreated()->json('data.id');

        // Rapprochement automatique à la soumission : décidé par le moteur.
        $automatic = $this->getJson("/api/invoices/{$invoiceId}/match-runs")->assertOk();
        $automatic->assertJsonPath('data.0.decided_by.actor_type', ActorType::System->value)
            ->assertJsonPath('data.0.trigger', 'invoice_submitted')
            ->assertJsonPath('data.0.engine_version', config('matching.engine_version'));

        // Relance manuelle : décidée par un utilisateur nommé.
        $manual = $this->postJson("/api/invoices/{$invoiceId}/match-runs")->assertCreated();
        $manual->assertJsonPath('data.decided_by.actor_type', ActorType::User->value)
            ->assertJsonPath('data.decided_by.actor_id', $accountant->id)
            ->assertJsonPath('data.decided_by.label', $accountant->name)
            ->assertJsonPath('data.trigger', 'manual');

        // Les tolérances appliquées et la preuve chiffrée sont archivées.
        $run = $this->getJson("/api/invoices/{$invoiceId}/match-runs/{$manual->json('data.id')}")->assertOk();
        $run->assertJsonPath('data.tolerance_snapshot.price_ratio', config('matching.tolerance.price_ratio'))
            ->assertJsonPath('data.line_results.0.evidence.quantity_ordered', 100)
            ->assertJsonPath('data.line_results.0.evidence.quantity_received', 60)
            ->assertJsonPath('data.line_results.0.evidence.quantity_available_for_matching', 60);

        $this->assertNotNull($run->json('data.evaluated_at'));
    }

    #[Test]
    public function rematching_an_invoice_never_overwrites_the_previous_audit_trail(): void
    {
        $this->actingAsRole('accountant');
        $this->givenPurchaseOrder([['quantity' => 100, 'price' => 10]]);
        $this->givenAcceptedDelivery([100]);

        $invoiceId = $this->postJson('/api/invoices', $this->invoicePayload([
            ['line' => 0, 'quantity' => 100, 'price' => 10],
        ]))->assertCreated()->json('data.id');

        $this->postJson("/api/invoices/{$invoiceId}/match-runs")->assertCreated();
        $this->postJson("/api/invoices/{$invoiceId}/match-runs")->assertCreated();

        $this->getJson("/api/invoices/{$invoiceId}/match-runs")
            ->assertOk()
            ->assertJsonCount(3, 'data');

        // Une seule autorisation reste active ; les précédentes sont conservées
        // en base au statut « remplacée ».
        $this->assertSame(1, PaymentAuthorization::query()->where('invoice_id', $invoiceId)->active()->count());
        $this->assertSame(
            2,
            PaymentAuthorization::query()
                ->where('invoice_id', $invoiceId)
                ->where('status', PaymentAuthorizationStatus::Superseded)
                ->count(),
        );
    }

    #[Test]
    public function a_cancelled_invoice_cannot_be_rematched(): void
    {
        $this->actingAsRole('accountant');
        $this->givenPurchaseOrder();
        $this->givenAcceptedDelivery([100]);

        $invoiceId = $this->postJson('/api/invoices', $this->invoicePayload([
            ['line' => 0, 'quantity' => 10, 'price' => 10],
        ]))->assertCreated()->json('data.id');

        $this->postJson("/api/invoices/{$invoiceId}/cancel")->assertOk();

        $this->postJson("/api/invoices/{$invoiceId}/match-runs")
            ->assertStatus(409)
            ->assertJsonPath('error_code', 'invoice_cancelled');
    }
}
