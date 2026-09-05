<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\HasApiTokens;
use Laravel\Sanctum\PersonalAccessToken;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string $password
 * @property Carbon|null $email_verified_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, LogsActivity, Notifiable;

    protected $fillable = ['name', 'email', 'password'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Identifiant du jeton porteur de la requete courante, ou `null`.
     *
     * Le jeton est relu depuis l'en-tete plutot que demande a
     * `currentAccessToken()` : Sanctum annonce la que le jeton est toujours
     * present, ce qui est faux — une requete authentifiee autrement (session de
     * test, garde `web`) n'en a aucun, et `Sanctum::actingAs` pose un jeton
     * transitoire depourvu d'identifiant. Passer par la recherche du porteur
     * rend l'absence explicite au lieu de la decouvrir par une erreur fatale.
     */
    public function currentAccessTokenId(Request $request): ?string
    {
        $bearer = $request->bearerToken();

        if ($bearer === null) {
            return null;
        }

        return PersonalAccessToken::findToken($bearer)?->getKey();
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'email'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
