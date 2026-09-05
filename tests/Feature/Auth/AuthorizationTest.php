<?php

namespace Tests\Feature\Auth;

use App\Domains\Matching\Enums\ReviewStatus;
use App\Domains\Receiving\Enums\DeliveryNoteStatus;
use App\Models\MatchException;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Concerns\BuildsProcurementScenario;
use Tests\TestCase;

/**
 * Authentification et séparation des tâches.
 *
 * Le point vraiment important ici : le comptable qui saisit les factures ne
 * doit PAS pouvoir arbitrer les écarts que ses propres saisies déclenchent.
 * Sans cette séparation, le contrôle à 3 voies serait purement décoratif.
 */
class AuthorizationTest extends TestCase
{
    use BuildsProcurementScenario, RefreshDatabase;

    #[Test]
    public function it_issues_a_token_on_valid_credentials(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::factory()->create(['email' => 'controleur@erp.test', 'password' => Hash::make('secret-pass')]);
        $user->assignRole('controller');

        $response = $this->postJson('/api/login', [
            'email' => 'controleur@erp.test',
            'password' => 'secret-pass',
        ])->assertOk();

        $this->assertNotEmpty($response->json('data.token'));
        $response->assertJsonPath('data.user.email', 'controleur@erp.test')
            ->assertJsonPath('data.user.roles.0', 'controller')
            ->assertJsonFragment(['matching.review']);
    }

    #[Test]
    public function it_rejects_invalid_credentials_without_revealing_whether_the_account_exists(): void
    {
        User::factory()->create(['email' => 'known@erp.test', 'password' => Hash::make('secret-pass')]);

        $wrongPassword = $this->postJson('/api/login', ['email' => 'known@erp.test', 'password' => 'nope']);
        $unknownEmail = $this->postJson('/api/login', ['email' => 'unknown@erp.test', 'password' => 'nope']);

        $wrongPassword->assertStatus(422)->assertJsonValidationErrors('email');
        $unknownEmail->assertStatus(422)->assertJsonValidationErrors('email');
        $this->assertSame($wrongPassword->json('errors.email'), $unknownEmail->json('errors.email'));
    }

    #[Test]
    public function protected_endpoints_answer_401_without_a_token(): void
    {
        $this->getJson('/api/invoices')
            ->assertStatus(401)
            ->assertJsonPath('error_code', 'unauthenticated');
    }

    #[Test]
    public function an_accountant_cannot_arbitrate_the_exceptions_they_triggered(): void
    {
        $this->actingAsRole('accountant');
        $this->givenPurchaseOrder([['quantity' => 100, 'price' => 10]]);
        $this->givenAcceptedDelivery([100]);

        $this->postJson('/api/invoices', $this->invoicePayload([
            ['line' => 0, 'quantity' => 100, 'price' => 15],
        ]))->assertCreated();

        $exception = MatchException::query()->firstOrFail();

        $this->postJson("/api/match-exceptions/{$exception->id}/review", [
            'decision' => ReviewStatus::Approved->value,
            'note' => 'Je valide ma propre saisie.',
        ])
            ->assertStatus(403)
            ->assertJsonPath('error_code', 'forbidden');
    }

    #[Test]
    public function a_warehouse_operator_cannot_submit_invoices(): void
    {
        $this->actingAsRole('warehouse');
        $this->givenPurchaseOrder();

        $this->postJson('/api/invoices', $this->invoicePayload([
            ['line' => 0, 'quantity' => 10, 'price' => 10],
        ]))->assertStatus(403);
    }

    #[Test]
    public function a_buyer_cannot_accept_the_deliveries_of_their_own_purchase_orders(): void
    {
        $this->actingAsRole('buyer');
        $this->givenPurchaseOrder();
        $deliveryNote = $this->givenDelivery([50], DeliveryNoteStatus::Draft);

        $this->postJson("/api/delivery-notes/{$deliveryNote->id}/review", ['status' => 'accepted'])
            ->assertStatus(403);
    }

    #[Test]
    public function logging_out_invalidates_the_token(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::factory()->create(['password' => Hash::make('secret-pass')]);
        $user->assignRole('controller');

        $token = $this->postJson('/api/login', ['email' => $user->email, 'password' => 'secret-pass'])
            ->json('data.token');

        $this->withHeader('Authorization', "Bearer {$token}")->postJson('/api/logout')->assertNoContent();

        $this->assertDatabaseCount('personal_access_tokens', 0);

        // Le guard Sanctum memorise l'utilisateur resolu pour la duree du
        // conteneur : sans cet oubli explicite, la requete suivante reutiliserait
        // la resolution precedente au lieu de rejouer l'authentification.
        $this->app['auth']->forgetGuards();

        $this->withHeader('Authorization', "Bearer {$token}")->getJson('/api/me')->assertStatus(401);
    }
}
