<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Rôles et permissions du circuit achats.
 *
 * Le découpage suit une règle de séparation des tâches : personne ne cumule
 * « je commande », « je réceptionne » et « je débloque le paiement ». C'est
 * cette séparation, autant que le rapprochement lui-même, qui rend la fraude
 * difficile — un contrôle à 3 voies exécuté de bout en bout par une seule
 * personne ne protège de rien.
 */
class RolesAndPermissionsSeeder extends Seeder
{
    /** @var list<string> */
    private const PERMISSIONS = [
        'procurement.view',
        'procurement.manage',
        'receiving.view',
        'receiving.manage',
        'invoicing.view',
        'invoicing.manage',
        'matching.view',
        'matching.run',
        'matching.review',
        'payments.view',
        // Constater un reglement : le geste comptable qui suit l'autorisation.
        'payments.manage',
        // Administration des comptes, separee du role d'administrateur pour
        // pouvoir la confier sans tout donner.
        'users.view',
        'users.manage',
    ];

    /** @var array<string, list<string>> */
    private const ROLES = [
        // Accès complet : administration technique et dépannage.
        'admin' => self::PERMISSIONS,

        // Acheteur : passe les commandes, suit ce qui arrive, ne touche ni aux
        // factures ni aux arbitrages.
        'buyer' => [
            'procurement.view',
            'procurement.manage',
            'receiving.view',
            'invoicing.view',
            'matching.view',
        ],

        // Magasinier : réceptionne et contrôle la marchandise. Ne voit ni prix
        // de facture ni autorisation de paiement.
        'warehouse' => [
            'procurement.view',
            'receiving.view',
            'receiving.manage',
        ],

        // Comptable fournisseurs : saisit les factures, relance un
        // rapprochement, mais ne peut pas arbitrer un écart qu'il a produit.
        'accountant' => [
            'procurement.view',
            'receiving.view',
            'invoicing.view',
            'invoicing.manage',
            'matching.view',
            'matching.run',
            'payments.view',
            'payments.manage',
        ],

        // Contrôleur financier : seul habilité à arbitrer un écart, donc seul
        // à pouvoir débloquer un paiement bloqué par le moteur.
        'controller' => [
            'procurement.view',
            'receiving.view',
            'invoicing.view',
            'matching.view',
            'matching.run',
            'matching.review',
            'payments.view',
        ],
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::PERMISSIONS as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        foreach (self::ROLES as $roleName => $permissions) {
            Role::findOrCreate($roleName, 'web')->syncPermissions($permissions);
        }
    }
}
