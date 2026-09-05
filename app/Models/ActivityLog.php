<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Spatie\Activitylog\Models\Activity as SpatieActivity;

/**
 * Entree du journal d'audit.
 *
 * Etendue uniquement pour porter une cle primaire en UUID : l'identifiant
 * d'une entree est expose par l'API (`/api/audit-logs/{auditLog}`), il suit
 * donc la meme regle que les autres.
 *
 * @property string $id
 */
class ActivityLog extends SpatieActivity
{
    use HasUuids;
}
