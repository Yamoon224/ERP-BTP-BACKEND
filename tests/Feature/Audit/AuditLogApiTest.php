<?php

namespace Tests\Feature\Audit;

use App\Models\ActivityLog;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Journal d'audit.
 *
 * Il est teste comme une piece de preuve, pas comme un ecran : ce qui compte
 * est qu'il enregistre l'avant et l'apres, qu'il nomme l'auteur, et qu'aucune
 * route ne permette de le retoucher. Un journal editable n'atteste de rien.
 */
class AuditLogApiTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_records_who_changed_what_and_serves_it_readably(): void
    {
        // L'acheteur modifie le referentiel, le comptable relit le journal :
        // produire un mouvement et l'auditer ne sont pas le meme metier.
        $buyer = $this->actingAsRole('buyer');
        $supplier = Supplier::factory()->create(['name' => 'Béton Express SAS']);

        $this->patchJson("/api/suppliers/{$supplier->id}", ['name' => 'Béton Express SA'])
            ->assertOk();

        $this->actingAsRole('accountant');

        $response = $this->getJson('/api/audit-logs?subject_type='.urlencode(Supplier::class))
            ->assertOk();

        /** @var list<array<string, mixed>> $entries */
        $entries = $response->json('data');
        $entry = null;

        foreach ($entries as $candidate) {
            if (($candidate['event'] ?? null) === 'updated') {
                $entry = $candidate;
                break;
            }
        }

        $this->assertNotNull($entry, "Le journal n'a rien enregistré pour la modification.");
        // Le type technique est traduit : « App\\Models\\Supplier » ne dit rien
        // a un controleur financier, « Fournisseur » si.
        $this->assertSame('Fournisseur', $entry['subject_label']);
        $this->assertSame('Modification', $entry['event_label']);
        $this->assertSame($supplier->id, $entry['subject_id']);
        $this->assertSame('Béton Express SA', $entry['properties']['attributes']['name']);
        $this->assertSame('Béton Express SAS', $entry['properties']['old']['name']);

        // L'auteur est nomme : une trace anonyme ne permet pas d'arbitrer.
        $this->assertSame($buyer->id, $entry['causer']['id'] ?? null);
        $this->assertSame($buyer->name, $entry['causer_label']);
    }

    #[Test]
    public function it_filters_by_event_and_by_period(): void
    {
        $this->actingAsRole('buyer');
        $supplier = Supplier::factory()->create();
        $this->patchJson("/api/suppliers/{$supplier->id}", ['name' => 'Nouveau nom'])->assertOk();

        $this->actingAsRole('accountant');

        $this->getJson('/api/audit-logs?event=created')
            ->assertOk()
            ->assertJsonPath('data.0.event', 'created');

        $this->getJson('/api/audit-logs?event=updated')
            ->assertOk()
            ->assertJsonPath('data.0.event', 'updated');

        // Une fenetre passee ne contient rien : le filtre porte bien sur la
        // date d'enregistrement et non sur un tri.
        $this->getJson('/api/audit-logs?to='.now()->subYear()->toDateString())
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    #[Test]
    public function it_publishes_the_filter_values_found_in_the_data(): void
    {
        $this->actingAsRole('buyer');
        Supplier::factory()->create();

        $this->actingAsRole('accountant');

        $facets = $this->getJson('/api/audit-logs/facets')->assertOk()->json('data');

        // Calculees sur les donnees reelles, pas codees en dur : la liste des
        // objets journalises depend de ce qui a effectivement bouge.
        $this->assertContains(Supplier::class, $facets['subject_types']);
        $this->assertContains('created', $facets['events']);
    }

    #[Test]
    public function the_journal_cannot_be_written_to_through_the_api(): void
    {
        $this->actingAsRole('buyer');
        Supplier::factory()->create();

        $entry = ActivityLog::query()->latest('id')->firstOrFail();

        $this->actingAsRole('admin');

        // 405 et non 403 : le chemin existe en lecture, mais aucun verbe
        // d'ecriture n'y est declare. Ce n'est donc pas une question de
        // permission — meme un administrateur n'a rien a refuser ici.
        $this->postJson('/api/audit-logs', ['description' => 'faux mouvement'])
            ->assertStatus(405);
        $this->patchJson("/api/audit-logs/{$entry->id}", ['description' => 'x'])
            ->assertStatus(405);
        $this->deleteJson("/api/audit-logs/{$entry->id}")->assertStatus(405);

        $this->assertDatabaseHas('activity_log', ['id' => $entry->id]);
    }

    #[Test]
    public function reading_the_journal_requires_its_own_permission(): void
    {
        // Le magasinier travaille dans le circuit sans avoir a en auditer les
        // mouvements : la permission est distincte de toutes les autres.
        $this->actingAsRole('warehouse');

        $this->getJson('/api/audit-logs')->assertForbidden();
    }
}
