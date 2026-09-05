<?php

namespace Tests\Feature\Matching;

use App\Domains\Matching\Services\InvoiceMatchingService;
use App\Models\Invoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Concerns\BuildsProcurementScenario;
use Tests\TestCase;

/**
 * Registre global des rapprochements.
 *
 * Le test le plus important de ce fichier est celui qui verifie qu'on ne peut
 * **pas** modifier ni supprimer une execution. Ce n'est pas une lacune
 * d'implementation : une execution archive qui a decide, quand, avec quelles
 * tolerances et sur quelle preuve chiffree. La retoucher reviendrait a reecrire
 * une decision passee ; l'effacer, a supprimer la justification d'un paiement
 * deja autorise. Le geste equivalent est de **rejouer**, ce qui ajoute une
 * execution sans en detruire aucune.
 */
class MatchRunRegistryTest extends TestCase
{
    use BuildsProcurementScenario, RefreshDatabase;

    private function givenMatchedInvoice(): Invoice
    {
        $this->givenPurchaseOrder([['quantity' => 10, 'price' => 100]]);
        $this->givenAcceptedDelivery([10]);

        $this->postJson(
            '/api/invoices',
            $this->invoicePayload([['line' => 0, 'quantity' => 10, 'price' => 100]]),
        )->assertCreated();

        return Invoice::firstOrFail();
    }

    #[Test]
    public function it_lists_every_run_with_its_invoice_context(): void
    {
        $this->actingAsRole('accountant');
        $invoice = $this->givenMatchedInvoice();

        $this->getJson('/api/match-runs')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.invoice_id', $invoice->id)
            // Sans sa facture, une execution ne dit rien de ce qu'elle a
            // decide : le registre la sert donc systematiquement.
            ->assertJsonPath('data.0.invoice.reference', $invoice->reference)
            ->assertJsonPath('data.0.invoice.supplier.name', $this->supplier->name)
            ->assertJsonPath('data.0.trigger', InvoiceMatchingService::TRIGGER_INVOICE_SUBMITTED);
    }

    #[Test]
    public function replaying_adds_a_run_instead_of_replacing_the_previous_one(): void
    {
        $this->actingAsRole('accountant');
        $invoice = $this->givenMatchedInvoice();

        $this->postJson("/api/invoices/{$invoice->id}/match-runs")->assertCreated();

        $response = $this->getJson('/api/match-runs')->assertOk();

        $this->assertCount(2, $response->json('data'));
        // Antichronologique : la derniere execution fait foi, elle vient en tete.
        $this->assertSame(
            InvoiceMatchingService::TRIGGER_MANUAL,
            $response->json('data.0.trigger'),
        );
        $this->assertSame(
            InvoiceMatchingService::TRIGGER_INVOICE_SUBMITTED,
            $response->json('data.1.trigger'),
        );
    }

    #[Test]
    public function it_filters_the_registry_by_verdict_and_by_author(): void
    {
        $this->actingAsRole('accountant');
        $invoice = $this->givenMatchedInvoice();
        $this->postJson("/api/invoices/{$invoice->id}/match-runs")->assertCreated();

        // La soumission est une decision du moteur, la relance celle d'une
        // personne : les deux ne s'auditent pas de la meme facon.
        $this->getJson('/api/match-runs?actor_type=system')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.decided_by.actor_type', 'system');

        $this->getJson('/api/match-runs?actor_type=user')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.decided_by.actor_type', 'user');

        $this->getJson('/api/match-runs?status=matched')->assertOk()->assertJsonCount(2, 'data');
        $this->getJson('/api/match-runs?status=exception')->assertOk()->assertJsonCount(0, 'data');
    }

    #[Test]
    public function it_serves_a_single_run_with_its_evidence(): void
    {
        $this->actingAsRole('accountant');
        $invoice = $this->givenMatchedInvoice();
        $run = $invoice->matchRuns()->firstOrFail();

        $this->getJson("/api/match-runs/{$run->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $run->id)
            ->assertJsonPath('data.engine_version', (string) config('matching.engine_version'))
            // La preuve chiffree accompagne chaque ligne : c'est elle qui
            // permet de refaire le calcul a la main des annees plus tard.
            ->assertJsonPath('data.line_results.0.evidence.quantity_ordered', 10)
            ->assertJsonPath('data.line_results.0.evidence.quantity_received', 10);
    }

    #[Test]
    public function a_run_can_be_neither_modified_nor_deleted(): void
    {
        $this->actingAsRole('admin');
        $invoice = $this->givenMatchedInvoice();
        $run = $invoice->matchRuns()->firstOrFail();

        // 405 et non 403 : le chemin existe en lecture, mais aucun verbe
        // d'ecriture n'y est declare. Ce n'est donc pas une question de
        // permission — meme un administrateur n'a rien a refuser ici.
        $this->patchJson("/api/match-runs/{$run->id}", ['status' => 'matched'])
            ->assertStatus(405);
        $this->putJson("/api/match-runs/{$run->id}", ['status' => 'matched'])
            ->assertStatus(405);
        $this->deleteJson("/api/match-runs/{$run->id}")->assertStatus(405);

        $this->assertDatabaseHas('match_runs', ['id' => $run->id]);
    }

    #[Test]
    public function a_warehouse_operator_cannot_read_the_registry(): void
    {
        $this->actingAsRole('warehouse');

        $this->getJson('/api/match-runs')->assertForbidden();
    }
}
