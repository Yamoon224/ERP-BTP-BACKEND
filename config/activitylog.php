<?php

use App\Models\ActivityLog;

/*
|--------------------------------------------------------------------------
| Journal d'activite
|--------------------------------------------------------------------------
|
| Seule la cle `activity_model` est redefinie ici : le reste de la
| configuration du paquet reste celle par defaut (Laravel fusionne la
| configuration du paquet sous celle de l'application). Le modele est
| surcharge pour une unique raison — une cle primaire en UUID, comme partout
| ailleurs dans ce schema.
|
*/

return [
    'activity_model' => ActivityLog::class,
];
