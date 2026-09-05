<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Spatie\Permission\Models\Role as SpatieRole;

/**
 * Role applicatif.
 *
 * Le modele du paquet est etendu pour la seule raison qui vaille ici : sa cle
 * primaire doit etre un UUID comme celle de toutes les autres tables, sans
 * quoi la table pivot `model_has_roles` melangerait un entier (le role) et un
 * UUID (l'utilisateur) — et une jointure sur deux types differents finit
 * toujours par se voir.
 *
 * @property string $id
 */
class Role extends SpatieRole
{
    use HasUuids;
}
