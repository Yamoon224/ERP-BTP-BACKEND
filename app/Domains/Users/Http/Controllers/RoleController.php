<?php

namespace App\Domains\Users\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Spatie\Permission\Models\Role;

/**
 * Roles assignables, avec leurs permissions.
 *
 * L'ecran d'administration a besoin de montrer ce qu'un role autorise avant
 * qu'on l'attribue : « acheteur » ne dit rien a qui ne connait pas le
 * decoupage, « peut creer des bons de commande » si.
 */
class RoleController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $roles = Role::query()
            ->with('permissions')
            ->orderBy('name')
            ->get()
            ->map(fn (Role $role): array => [
                'name' => $role->name,
                'permissions' => $role->permissions->pluck('name')->all(),
            ]);

        return response()->json(['data' => $roles]);
    }
}
