<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;
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
        // Referentiel des devises et des taux. Consulter un taux est banal ;
        // en saisir un ne l'est pas : un taux manuel deplace directement le
        // montant autorise au paiement, la ou le rapprochement ne fait que le
        // constater. Les deux permissions sont donc distinctes.
        'currencies.view',
        'currencies.manage',
        // Journal d'audit. En lecture seule pour tout le monde, y compris
        // l'administrateur : personne ne peut effacer une trace.
        'audit.view',
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
            'currencies.view',
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
            // Le comptable fournisseurs saisit les cotations du jour : c'est
            // son metier de savoir a quel taux une facture en devise se regle.
            'currencies.view',
            'currencies.manage',
            'audit.view',
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
            'currencies.view',
            // Le controleur arbitre les ecarts : il doit pouvoir remonter la
            // chaine des modifications qui les ont produits.
            'audit.view',
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
