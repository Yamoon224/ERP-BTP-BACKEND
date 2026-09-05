<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Spatie\Permission\Models\Permission as SpatiePermission;

/**
 * Permission granulaire. Etendue pour la meme raison que [[Role]] : cle
 * primaire en UUID, alignee sur le reste du schema.
 *
 * @property string $id
 */
class Permission extends SpatiePermission
{
    use HasUuids;
}
