<?php

namespace Tests\Feature\Users;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Administration des comptes.
 *
 * L'enjeu n'est pas le CRUD lui-meme mais ce qui l'entoure : seul un compte
 * habilite y accede, et deux suppressions doivent etre refusees parce qu'elles
 * laisseraient l'application sans administrateur — ou son operateur dehors.
 */
class UserAdministrationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function an_administrator_lists_paginates_and_filters_accounts(): void
    {
        $this->actingAsRole('admin');
        User::factory()->count(3)->create()->each->assignRole('buyer');
        User::factory()->create(['name' => 'Zoe Comptable'])->assignRole('accountant');

        $this->getJson('/api/users?role=buyer')
            ->assertOk()
            ->assertJsonPath('meta.total', 3)
            ->assertJsonPath('data.0.roles.0', 'buyer');

        $this->getJson('/api/users?search=Zoe')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.name', 'Zoe Comptable');
    }

    #[Test]
    public function it_sorts_accounts_on_an_allowlisted_column_only(): void
    {
        $this->actingAsRole('admin');
        User::factory()->create(['name' => 'Aaron Premier'])->assignRole('buyer');
        User::factory()->create(['name' => 'Zoe Derniere'])->assignRole('buyer');

        $this->getJson('/api/users?sort=name&direction=asc')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Aaron Premier');

        // Une cle inconnue ne doit ni casser ni atteindre le SQL : elle est
        // simplement ignoree au profit du tri par defaut.
        $this->getJson('/api/users?sort=password);DROP TABLE users;--&direction=asc')
            ->assertOk();

        $this->assertDatabaseCount('users', 3);
    }

    #[Test]
    public function it_creates_an_account_with_its_roles(): void
    {
        $this->actingAsRole('admin');

        $this->postJson('/api/users', [
            'name' => 'Nouvelle Recrue',
            'email' => 'recrue@erp.test',
            'password' => 'mot-de-passe-solide',
            'roles' => ['warehouse'],
        ])
            ->assertCreated()
            ->assertJsonPath('data.email', 'recrue@erp.test')
            ->assertJsonPath('data.roles.0', 'warehouse')
            ->assertJsonFragment(['receiving.manage']);

        $this->assertTrue(Hash::check('mot-de-passe-solide', User::whereEmail('recrue@erp.test')->sole()->password));
    }

    #[Test]
    public function it_requires_at_least_one_role(): void
    {
        $this->actingAsRole('admin');

        $this->postJson('/api/users', [
            'name' => 'Sans Role',
            'email' => 'sans-role@erp.test',
            'password' => 'mot-de-passe-solide',
            'roles' => [],
        ])->assertStatus(422)->assertJsonValidationErrors('roles');
    }

    #[Test]
    public function an_empty_password_on_update_keeps_the_existing_one(): void
    {
        $this->actingAsRole('admin');
        $target = User::factory()->create(['password' => Hash::make('ancien-mot-de-passe')]);
        $target->assignRole('buyer');

        $this->patchJson("/api/users/{$target->id}", [
            'name' => 'Nom Corrige',
            'password' => null,
            'roles' => ['controller'],
        ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Nom Corrige')
            ->assertJsonPath('data.roles.0', 'controller');

        $this->assertTrue(Hash::check('ancien-mot-de-passe', $target->refresh()->password));
    }

    #[Test]
    public function it_refuses_to_delete_the_account_in_use(): void
    {
        $administrator = $this->actingAsRole('admin');

        $this->deleteJson("/api/users/{$administrator->id}")
            ->assertStatus(409)
            ->assertJsonPath('error_code', 'cannot_delete_self');

        $this->assertModelExists($administrator);
    }

    #[Test]
    public function it_refuses_to_delete_the_last_administrator(): void
    {
        $this->actingAsRole('admin');
        $otherAdministrator = User::factory()->create();
        $otherAdministrator->assignRole('admin');

        // Deux administrateurs : la suppression du second est legitime.
        $this->deleteJson("/api/users/{$otherAdministrator->id}")->assertNoContent();

        // Il n'en reste qu'un — celui qui opere — et il est indeboulonnable.
        $lastStanding = User::role('admin')->sole();
        $this->assertSame(1, User::role('admin')->count());
        $this->assertModelExists($lastStanding);
    }

    #[Test]
    public function deleting_an_account_revokes_its_tokens(): void
    {
        $this->actingAsRole('admin');
        $target = User::factory()->create();
        $target->assignRole('buyer');
        $target->createToken('web');

        $this->deleteJson("/api/users/{$target->id}")->assertNoContent();

        $this->assertDatabaseMissing('personal_access_tokens', ['tokenable_id' => $target->id]);
        $this->assertModelMissing($target);
    }

    #[Test]
    public function an_account_without_the_permission_cannot_reach_the_administration(): void
    {
        $this->actingAsRole('accountant');

        $this->getJson('/api/users')->assertStatus(403)->assertJsonPath('error_code', 'forbidden');
        $this->postJson('/api/users', [])->assertStatus(403);
        $this->getJson('/api/roles')->assertStatus(403);
    }

    #[Test]
    public function it_exposes_the_assignable_roles_with_their_permissions(): void
    {
        $this->actingAsRole('admin');
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->getJson('/api/roles')
            ->assertOk()
            ->assertJsonCount(5, 'data')
            ->assertJsonFragment(['name' => 'controller']);
    }
}
