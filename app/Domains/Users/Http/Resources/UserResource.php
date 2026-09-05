<?php

namespace App\Domains\Users\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Vue administrateur d'un compte : identite, roles et droits effectifs.
 *
 * Distincte de la ressource d'authentification : celle-ci decrit **un autre**
 * utilisateur, vu depuis l'ecran d'administration, et porte donc la date de
 * creation qui n'a aucun interet pour la session courante.
 *
 * @mixin User
 */
class UserResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'roles' => $this->getRoleNames()->all(),
            'permissions' => $this->getAllPermissions()->pluck('name')->all(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
