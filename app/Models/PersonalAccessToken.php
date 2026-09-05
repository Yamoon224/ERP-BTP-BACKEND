<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Laravel\Sanctum\PersonalAccessToken as SanctumPersonalAccessToken;

/**
 * Jeton d'acces personnel.
 *
 * L'identifiant du jeton voyage en clair dans l'en-tete `Authorization`
 * (« {id}|{secret} ») : un entier sequentiel y annoncerait combien de jetons
 * ont ete emis depuis la mise en service. L'UUID ne dit rien de tel.
 *
 * @property string $id
 */
class PersonalAccessToken extends SanctumPersonalAccessToken
{
    use HasUuids;
}
