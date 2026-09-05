<?php

namespace Tests\Feature\Users;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Compte de l'utilisateur courant.
 *
 * Ces routes n'exigent aucune permission — et c'est precisement ce qu'il faut
 * verifier : elles ne doivent agir que sur l'appelant, jamais sur un autre
 * compte, et ne doivent pas offrir de raccourci vers une elevation de droits.
 */
class ProfileTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function a_user_updates_their_own_name_and_email(): void
    {
        $user = $this->actingAsRole('accountant');

        $this->patchJson('/api/me', [
            'name' => 'Julien Bardot',
            'email' => 'julien.bardot@erp.test',
        ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Julien Bardot')
            ->assertJsonPath('data.email', 'julien.bardot@erp.test')
            ->assertJsonPath('data.roles.0', 'accountant');

        $this->assertSame('julien.bardot@erp.test', $user->refresh()->email);
    }

    #[Test]
    public function updating_ones_profile_never_grants_a_role(): void
    {
        $user = $this->actingAsRole('warehouse');

        $this->patchJson('/api/me', [
            'name' => 'Sofia Ferreira',
            'email' => 'sofia@erp.test',
            'roles' => ['admin'],
            'password' => 'tentative-de-contournement',
        ])->assertOk();

        $user->refresh();
        $this->assertSame(['warehouse'], $user->getRoleNames()->all());
        $this->assertFalse(Hash::check('tentative-de-contournement', $user->password));
    }

    #[Test]
    public function an_email_already_taken_is_refused_but_ones_own_is_accepted(): void
    {
        $user = $this->actingAsRole('buyer');
        User::factory()->create(['email' => 'deja-pris@erp.test']);

        $this->patchJson('/api/me', ['name' => $user->name, 'email' => 'deja-pris@erp.test'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('email');

        $this->patchJson('/api/me', ['name' => 'Nom Change', 'email' => $user->email])
            ->assertOk()
            ->assertJsonPath('data.name', 'Nom Change');
    }

    #[Test]
    public function a_user_changes_their_password_by_proving_the_current_one(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::factory()->create(['password' => Hash::make('ancien-mot-de-passe')]);
        $user->assignRole('controller');
        $this->actingAs($user, 'sanctum');

        $this->putJson('/api/me/password', [
            'current_password' => 'ancien-mot-de-passe',
            'password' => 'nouveau-mot-de-passe',
            'password_confirmation' => 'nouveau-mot-de-passe',
        ])->assertOk();

        $this->assertTrue(Hash::check('nouveau-mot-de-passe', $user->refresh()->password));
    }

    #[Test]
    public function a_wrong_current_password_blocks_the_change(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::factory()->create(['password' => Hash::make('ancien-mot-de-passe')]);
        $user->assignRole('controller');
        $this->actingAs($user, 'sanctum');

        $this->putJson('/api/me/password', [
            'current_password' => 'pas-le-bon',
            'password' => 'nouveau-mot-de-passe',
            'password_confirmation' => 'nouveau-mot-de-passe',
        ])->assertStatus(422)->assertJsonValidationErrors('current_password');

        $this->assertTrue(Hash::check('ancien-mot-de-passe', $user->refresh()->password));
    }

    #[Test]
    public function a_mismatched_confirmation_blocks_the_change(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::factory()->create(['password' => Hash::make('ancien-mot-de-passe')]);
        $user->assignRole('buyer');
        $this->actingAs($user, 'sanctum');

        $this->putJson('/api/me/password', [
            'current_password' => 'ancien-mot-de-passe',
            'password' => 'nouveau-mot-de-passe',
            'password_confirmation' => 'pas-la-meme-chose',
        ])->assertStatus(422)->assertJsonValidationErrors('password');
    }
}
