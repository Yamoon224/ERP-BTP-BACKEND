<?php

namespace App\Domains\Users\Http\Controllers;

use App\Domains\Auth\Http\Resources\UserResource;
use App\Domains\Users\Http\Requests\UpdatePasswordRequest;
use App\Domains\Users\Http\Requests\UpdateProfileRequest;
use App\Domains\Users\Services\UserService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/**
 * Compte de l'utilisateur connecte.
 *
 * Distinct de `UserController` : ces routes ne demandent aucune permission
 * d'administration, mais n'agissent jamais que sur `$request->user()`. Faire
 * porter les deux usages par le meme endpoint reviendrait a faire dependre la
 * securite d'une comparaison d'identifiants dans un controleur.
 */
class ProfileController extends Controller
{
    public function __construct(private readonly UserService $users) {}

    public function update(UpdateProfileRequest $request): UserResource
    {
        return new UserResource($this->users->updateOwnProfile(
            $request->user(),
            $request->validated(),
        ));
    }

    public function updatePassword(UpdatePasswordRequest $request): JsonResponse
    {
        $user = $request->user();

        // Le jeton courant survit au changement : deconnecter l'utilisateur de
        // l'onglet ou il vient de changer son mot de passe serait absurde.
        $this->users->updateOwnPassword(
            $user,
            $request->string('password')->value(),
            $user->currentAccessToken()->getKey(),
        );

        return response()->json([
            'data' => ['message' => 'Mot de passe mis a jour. Vos autres sessions ont ete fermees.'],
        ]);
    }
}
