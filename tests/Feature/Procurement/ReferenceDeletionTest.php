<?php

namespace Tests\Feature\Procurement;

use App\Models\Project;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Suppression au referentiel achats.
 *
 * La regle testee ici n'est pas une contrainte technique de cle etrangere,
 * c'est une regle de tracabilite : une decision archivee doit rester relisible.
 * Une facture rapprochee il y a deux ans dont le fournisseur a disparu du
 * referentiel n'explique plus rien.
 */
class ReferenceDeletionTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function a_buyer_deletes_a_supplier_that_no_document_refers_to(): void
    {
        $this->actingAsRole('buyer');
        $supplier = Supplier::factory()->create();

        $this->deleteJson("/api/suppliers/{$supplier->id}")->assertNoContent();

        $this->assertDatabaseMissing('suppliers', ['id' => $supplier->id]);
    }

    #[Test]
    public function it_refuses_to_delete_a_supplier_cited_by_a_purchase_order(): void
    {
        $this->actingAsRole('buyer');
        $supplier = Supplier::factory()->create();
        PurchaseOrder::factory()->create([
            'supplier_id' => $supplier->id,
            'project_id' => Project::factory()->create()->id,
        ]);

        $this->deleteJson("/api/suppliers/{$supplier->id}")
            ->assertStatus(409)
            ->assertJsonPath('error_code', 'supplier_in_use');

        $this->assertDatabaseHas('suppliers', ['id' => $supplier->id]);
    }

    #[Test]
    public function deactivating_is_the_way_out_when_deletion_is_refused(): void
    {
        $this->actingAsRole('buyer');
        $supplier = Supplier::factory()->create(['is_active' => true]);
        PurchaseOrder::factory()->create([
            'supplier_id' => $supplier->id,
            'project_id' => Project::factory()->create()->id,
        ]);

        // Le fournisseur quitte les listes de saisie sans que l'historique
        // perde son emetteur : c'est le geste que le refus 409 recommande.
        $this->patchJson("/api/suppliers/{$supplier->id}", ['is_active' => false])
            ->assertOk()
            ->assertJsonPath('data.is_active', false);
    }

    #[Test]
    public function a_buyer_deletes_a_project_that_carries_nothing(): void
    {
        $this->actingAsRole('buyer');
        $project = Project::factory()->create();

        $this->deleteJson("/api/projects/{$project->id}")->assertNoContent();

        $this->assertDatabaseMissing('projects', ['id' => $project->id]);
    }

    #[Test]
    public function it_refuses_to_delete_a_project_already_committed(): void
    {
        $this->actingAsRole('buyer');
        $project = Project::factory()->create();
        PurchaseOrder::factory()->create([
            'supplier_id' => Supplier::factory()->create()->id,
            'project_id' => $project->id,
        ]);

        $this->deleteJson("/api/projects/{$project->id}")
            ->assertStatus(409)
            ->assertJsonPath('error_code', 'project_in_use');
    }

    #[Test]
    public function a_warehouse_operator_cannot_delete_a_supplier(): void
    {
        // Le magasinier voit le referentiel pour saisir ses receptions ; il ne
        // le modifie pas. La separation des taches se verifie ici aussi.
        $this->actingAsRole('warehouse');
        $supplier = Supplier::factory()->create();

        $this->deleteJson("/api/suppliers/{$supplier->id}")->assertForbidden();
    }
}
