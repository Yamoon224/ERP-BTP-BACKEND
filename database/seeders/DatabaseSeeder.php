<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RolesAndPermissionsSeeder::class,
            // Les taux precedent les documents : un rapprochement sans taux
            // bloquerait les factures libellees dans une autre devise.
            ExchangeRateSeeder::class,
            DemoDataSeeder::class,
            // Le volume vient apres les cas de demonstration : ceux-ci doivent
            // garder leurs references lisibles (PO-2026-0001…) en tete de
            // liste, et non se perdre au milieu de 150 documents generes.
            VolumeDemoSeeder::class,
        ]);
    }
}
