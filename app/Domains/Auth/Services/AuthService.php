<?php

namespace App\Domains\Auth\Services;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * Authentification par token Sanctum : le frontend NextJS et l API sont deux
 * origines distinctes sans session partagee, le mode cookie/SPA ne s applique
 * donc pas ici.
 */
final class AuthService
{
    /**
     * @param  array{email: string, password: string}  $credentials
     * @return array{user: User, token: string}
     *
     * @throws ValidationException
     */
    public function attempt(array $credentials, string $deviceName = 'api'): array
    {
        if (! Auth::validate($credentials)) {
            // Message volontairement identique que l email existe ou non :
            // distinguer les deux cas transformerait le formulaire en oracle
            // d enumeration de comptes.
            throw ValidationException::withMessages([
                'email' => ['Identifiants invalides.'],
            ]);
        }

        /** @var User $user */
        $user = User::where('email', $credentials['email'])->firstOrFail();

        return [
            'user' => $user,
            'token' => $user->createToken($deviceName)->plainTextToken,
        ];
    }

    public function logout(User $user): void
    {
        // La route de deconnexion est protegee par auth:sanctum en mode token :
        // un token personnel existe donc toujours a ce stade.
        $user->currentAccessToken()->delete();
    }
}
