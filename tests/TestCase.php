<?php

namespace Tests;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Authentifie un utilisateur portant le rôle demandé.
     *
     * Les tests d'intégration passent tous par un rôle réel plutôt que par un
     * utilisateur tout-puissant : c'est la seule façon de vérifier au passage
     * que la séparation des tâches tient (un comptable ne doit pas pouvoir
     * arbitrer un écart qu'il a lui-même provoqué).
     */
    protected function actingAsRole(string $role): User
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $user = User::factory()->create();
        $user->assignRole($role);
        $this->actingAs($user);

        return $user;
    }
}
