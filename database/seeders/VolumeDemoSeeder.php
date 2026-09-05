<?php

namespace Database\Seeders;

use App\Domains\Invoicing\Services\InvoiceService;
use App\Domains\Payments\Contracts\PaymentAuthorizationRepositoryContract;
use App\Domains\Procurement\Services\PurchaseOrderService;
use App\Domains\Receiving\Enums\DeliveryNoteStatus;
use App\Domains\Receiving\Services\DeliveryNoteService;
use App\Models\DeliveryNote;
use App\Models\Invoice;
use App\Models\PaymentAuthorization;
use App\Models\Project;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Jeu de donnees de volume : 150 lignes dans chaque table metier.
 *
 * Le seeder de demonstration (`DemoDataSeeder`) montre les cas limites du
 * moteur, un par un, sur des chiffres qu'on peut verifier a la main. Celui-ci
 * repond a une autre question : est-ce que les ecrans, la pagination, le tri et
 * la file de revue tiennent devant un volume realiste ? Les deux coexistent
 * donc plutot que de se remplacer.
 *
 * Comme le seeder de demonstration, tout passe par les services applicatifs :
 * les rapprochements, les ecarts et les autorisations de paiement ne sont pas
 * fabriques a la main, ils sont **produits** par le meme code qu'en production.
 * Un jeu de donnees ecrit directement en base finirait toujours par decrire un
 * etat que l'application ne sait pas atteindre.
 */
class VolumeDemoSeeder extends Seeder
{
    /** Cible par table. */
    private const TARGET = 150;

    /** @var array<string, string> */
    private const ROLE_NAMES = [
        'admin' => 'Administration',
        'buyer' => 'Achats',
        'warehouse' => 'Magasin',
        'accountant' => 'Comptabilite',
        'controller' => 'Controle financier',
    ];

    /** @var list<array{0: string, 1: string, 2: string}> item_code, description, unite */
    private const CATALOGUE = [
        ['CIM-42', 'Ciment CEM II 42,5 — sac 35 kg', 'sac'],
        ['SAB-01', 'Sable 0/4 lave', 't'],
        ['GRA-1020', 'Gravier 10/20 concasse', 't'],
        ['ACI-HA12', 'Acier HA12 — barre 12 m', 'u'],
        ['ACI-HA16', 'Acier HA16 — barre 12 m', 'u'],
        ['PAR-BET', 'Parpaing beton 20x20x50', 'u'],
        ['BOI-COF', 'Contreplaque de coffrage 18 mm', 'm2'],
        ['LOC-PEL', 'Location pelle 20 t — journee', 'j'],
        ['LOC-GRU', 'Location grue a tour — semaine', 'sem'],
        ['MOR-COL', 'Mortier colle — sac 25 kg', 'sac'],
        ['ENR-BB', 'Enrobe bitumineux BBSG 0/10', 't'],
        ['TUY-PVC', 'Tuyau PVC assainissement DN200', 'ml'],
        ['ISO-LR', 'Isolant laine de roche 100 mm', 'm2'],
        ['PEI-FAC', 'Peinture facade — seau 15 L', 'seau'],
        ['ECH-MOB', 'Echafaudage mobile — location mois', 'mois'],
    ];

    /** @var list<string> */
    private const VILLES = [
        'Nantes', 'Rennes', 'Lyon', 'Bordeaux', 'Lille', 'Toulouse', 'Rouen', 'Dijon',
        'Angers', 'Reims', 'Brest', 'Caen', 'Metz', 'Nimes', 'Tours', 'Amiens',
        'Perpignan', 'Besancon', 'Orleans', 'Mulhouse', 'Dakar', 'Abidjan', 'Bamako',
    ];

    /** @var list<string> */
    private const PRENOMS = [
        'Awa', 'Marc', 'Sofia', 'Julien', 'Nadia', 'Thomas', 'Ines', 'Karim', 'Lucie',
        'Mehdi', 'Claire', 'Antoine', 'Fatou', 'Paul', 'Emma', 'Yanis', 'Sarah',
        'Olivier', 'Chloe', 'Bilal', 'Camille', 'Hugo', 'Leila', 'Damien', 'Manon',
    ];

    /** @var list<string> */
    private const NOMS = [
        'Diop', 'Lemoine', 'Ferreira', 'Bardot', 'Belkacem', 'Morel', 'Nguyen', 'Fontaine',
        'Bertrand', 'Roussel', 'Sanchez', 'Leroy', 'Traore', 'Dumas', 'Faure', 'Girard',
        'Perrin', 'Marchand', 'Blanchard', 'Guerin', 'Robin', 'Chevalier', 'Renard',
    ];

    public function run(): void
    {
        // Graine fixe : deux executions du seeder produisent le meme jeu, ce
        // qui rend une capture d'ecran ou un bug reproductible.
        mt_srand(20260904);

        $users = $this->seedUsers();
        $suppliers = $this->seedSuppliers();
        $projects = $this->seedProjects();

        $purchaseOrders = $this->seedPurchaseOrders($users, $suppliers, $projects);
        $this->seedDeliveryNotes($users, $purchaseOrders);
        $this->seedInvoices($users, $purchaseOrders);
        $this->seedSettlements($users);
    }

    /**
     * 150 comptes repartis sur les cinq roles. Le mot de passe est le meme
     * pour tous (« password ») : c'est un jeu de demonstration, et un mot de
     * passe different par compte n'apporterait ici qu'une friction.
     *
     * @return array<string, list<User>> comptes indexes par role
     */
    private function seedUsers(): array
    {
        $roles = array_keys(self::ROLE_NAMES);
        $missing = max(0, self::TARGET - User::count());

        for ($index = 0; $index < $missing; $index++) {
            $role = $roles[$index % count($roles)];
            $name = $this->pick(self::PRENOMS).' '.$this->pick(self::NOMS);
            $email = $this->slug($name).'.'.($index + 1).'@erp.test';

            $user = User::firstOrCreate(
                ['email' => $email],
                ['name' => $name, 'password' => Hash::make('password'), 'email_verified_at' => now()],
            );
            $user->syncRoles([$role]);
        }

        $byRole = [];
        foreach ($roles as $role) {
            $byRole[$role] = User::role($role)->get()->all();
        }

        return $byRole;
    }

    /** @return list<Supplier> */
    private function seedSuppliers(): array
    {
        $formes = ['SAS', 'SARL', 'SA', 'EURL', 'SASU'];
        $activites = ['Materiaux', 'Beton', 'Charpente', 'Location', 'Terrassement', 'Etancheite', 'Menuiserie', 'Electricite'];
        $missing = max(0, self::TARGET - Supplier::count());

        for ($index = 0; $index < $missing; $index++) {
            $ville = $this->pick(self::VILLES);
            $activite = $this->pick($activites);
            $name = "{$activite} {$ville} ".$this->pick($formes);

            Supplier::create([
                'code' => sprintf('SUP-%04d', 1000 + $index),
                'name' => $name,
                'vat_number' => 'FR'.mt_rand(10, 99).mt_rand(100000000, 999999999),
                'email' => 'facturation@'.$this->slug($activite.'-'.$ville).'.example',
                // Un referentiel reel comporte des fournisseurs desactives :
                // les ecrans doivent savoir les montrer sans les proposer.
                'is_active' => $index % 12 !== 0,
            ]);
        }

        return Supplier::where('is_active', true)->get()->all();
    }

    /** @return list<Project> */
    private function seedProjects(): array
    {
        $ouvrages = ['Viaduc', 'Lycee', 'Hopital', 'Residence', 'Parking', 'Station', 'Gymnase', 'Mediatheque', 'Passerelle', 'Entrepot'];
        $clients = ['Conseil Departemental', 'Ville de', 'Region', 'Metropole de', 'Bailleur social', 'Groupe Immobilier'];
        $missing = max(0, self::TARGET - Project::count());

        for ($index = 0; $index < $missing; $index++) {
            $ville = $this->pick(self::VILLES);

            Project::create([
                'code' => sprintf('CH-%04d', 1000 + $index),
                'name' => 'Chantier '.$this->pick($ouvrages).' — '.$ville,
                'client_name' => $this->pick($clients).' '.$ville,
                'is_active' => $index % 15 !== 0,
            ]);
        }

        return Project::where('is_active', true)->get()->all();
    }

    /**
     * @param  array<string, list<User>>  $users
     * @param  list<Supplier>  $suppliers
     * @param  list<Project>  $projects
     * @return list<PurchaseOrder>
     */
    private function seedPurchaseOrders(array $users, array $suppliers, array $projects): array
    {
        $service = app(PurchaseOrderService::class);
        $missing = max(0, self::TARGET - PurchaseOrder::count());
        $created = [];

        for ($index = 0; $index < $missing; $index++) {
            $supplier = $this->pick($suppliers);
            $currency = $this->currencyFor($index);

            $lines = [];
            foreach ($this->pickMany(self::CATALOGUE, mt_rand(1, 4)) as [$code, $description, $unit]) {
                $lines[] = [
                    'item_code' => $code,
                    'description' => $description,
                    'unit' => $unit,
                    'quantity_ordered' => mt_rand(10, 800),
                    'unit_price' => $this->priceFor($currency),
                ];
            }

            $created[] = $service->create([
                'reference' => sprintf('PO-2026-%04d', 1000 + $index),
                'supplier_id' => $supplier->id,
                'project_id' => $this->pick($projects)->id,
                'currency' => $currency,
                'ordered_at' => now()->subDays(mt_rand(30, 210))->toDateString(),
                'notes' => $index % 7 === 0 ? 'Livraison sur site, acces poids lourds par le portail nord.' : null,
            ] + ['lines' => $lines], $this->pick($users['buyer']));
        }

        return $created;
    }

    /**
     * Un bon de livraison par bon de commande, avec trois issues : accepte
     * (le cas courant), en attente de controle, refuse. Les livraisons
     * partielles sont volontairement nombreuses — c'est la situation qui
     * produit les rapprochements les plus interessants a regarder.
     *
     * @param  array<string, list<User>>  $users
     * @param  list<PurchaseOrder>  $purchaseOrders
     */
    private function seedDeliveryNotes(array $users, array $purchaseOrders): void
    {
        $service = app(DeliveryNoteService::class);
        $budget = max(0, self::TARGET - DeliveryNote::count());
        $index = 0;

        foreach ($purchaseOrders as $purchaseOrder) {
            if ($index >= $budget) {
                break;
            }

            $orderLines = $purchaseOrder->lines()->orderBy('line_number')->get();
            $ratio = match ($index % 5) {
                0, 1, 2 => 1.0,          // recu conforme
                3 => 0.6,                // livraison partielle
                default => 0.85,
            };

            $deliveryNote = $service->record([
                'reference' => sprintf('BL-2026-%04d', 1000 + $index),
                'purchase_order_id' => $purchaseOrder->id,
                'received_at' => now()->subDays(mt_rand(5, 25))->toDateString(),
                'notes' => null,
                'lines' => $orderLines->map(fn ($line): array => [
                    'purchase_order_line_id' => $line->id,
                    'quantity_received' => round((float) $line->quantity_ordered * $ratio, 2),
                ])->all(),
            ], $this->pick($users['warehouse']));

            // 9 sur 10 controles, dont un refuse : il faut des BL en brouillon
            // pour que l'ecran de reception ait quelque chose a faire, mais un
            // BL non controle ne rend rien payable — trop nombreux, ils
            // videraient l'ecran des paiements.
            $decision = match ($index % 12) {
                7 => DeliveryNoteStatus::Rejected,
                11 => null,
                default => DeliveryNoteStatus::Accepted,
            };

            if ($decision !== null) {
                $service->review($deliveryNote, $decision, $this->pick($users['warehouse']));
            }

            $index++;
        }
    }

    /**
     * Une facture par bon de commande, en faisant varier volontairement le
     * prix et la quantite : c'est ce qui peuple la file des ecarts et donne
     * des autorisations de paiement partielles.
     *
     * @param  array<string, list<User>>  $users
     * @param  list<PurchaseOrder>  $purchaseOrders
     */
    private function seedInvoices(array $users, array $purchaseOrders): void
    {
        $service = app(InvoiceService::class);
        $budget = max(0, self::TARGET - Invoice::count());
        $index = 0;

        foreach ($purchaseOrders as $purchaseOrder) {
            if ($index >= $budget) {
                break;
            }

            $purchaseOrder->refresh();

            if (! $purchaseOrder->status->acceptsDocuments()) {
                continue;
            }

            $orderLines = $purchaseOrder->lines()->orderBy('line_number')->get();

            // Cinq profils de facture : conforme, ecart de prix minime qui
            // doit passer sans alerter, sur-facturation en quantite (la
            // portion saine reste payable), et — une fois sur cinq seulement —
            // un ecart de prix au-dela de la tolerance, qui bloque tout.
            [$priceFactor, $quantityFactor] = match ($index % 5) {
                0, 1 => [1.0, 1.0],
                2 => [1.004, 1.0],
                3 => [1.0, 1.15],
                default => [1.14, 1.0],
            };

            $lines = $orderLines->map(fn ($line): array => [
                'purchase_order_line_id' => $line->id,
                'description' => $line->description,
                'quantity' => round((float) $line->quantity_ordered * $quantityFactor, 2),
                'unit_price' => round((float) $line->unit_price * $priceFactor, 2),
            ])->all();

            $service->submit([
                'reference' => sprintf('FAC-2026-%04d', 1000 + $index),
                'purchase_order_id' => $purchaseOrder->id,
                'currency' => $purchaseOrder->currency->value,
                'invoice_date' => now()->subDays(mt_rand(1, 20))->toDateString(),
                'due_date' => now()->addDays(mt_rand(10, 45))->toDateString(),
                'lines' => $lines,
            ], $this->pick($users['accountant']));

            $index++;
        }
    }

    /**
     * Regle une autorisation sur trois. Sans cela, l'ecran des paiements
     * n'aurait qu'un seul etat a montrer, et le bouton « regler » serait la
     * seule chose qu'on puisse y observer.
     *
     * @param  array<string, list<User>>  $users
     */
    private function seedSettlements(array $users): void
    {
        $repository = app(PaymentAuthorizationRepositoryContract::class);

        $authorizations = PaymentAuthorization::query()
            ->active()
            ->unsettled()
            ->where('amount', '>', 0)
            ->orderBy('id')
            ->get();

        foreach ($authorizations as $position => $authorization) {
            if ($position % 3 !== 0) {
                continue;
            }

            $repository->settle($authorization, [
                'payment_reference' => sprintf('VIR-2026-%05d', 10000 + $authorization->id),
                'payment_method' => 'transfer',
                'settled_at' => now()->subDays(mt_rand(1, 12))->toDateTimeString(),
            ], $this->pick($users['accountant']));
        }
    }

    /**
     * Un fournisseur sur six facture dans sa propre devise : le systeme doit
     * etre exerce en multidevise, pas seulement en euros.
     */
    private function currencyFor(int $index): string
    {
        return match ($index % 6) {
            4 => 'USD',
            5 => 'XOF',
            default => 'EUR',
        };
    }

    /** Le franc CFA n'a pas de centime : ses prix sont d'un autre ordre de grandeur. */
    private function priceFor(string $currency): float
    {
        return $currency === 'XOF'
            ? (float) mt_rand(2000, 90000)
            : round(mt_rand(150, 90000) / 100, 2);
    }

    /**
     * @template T
     *
     * @param  list<T>  $values
     * @return T
     */
    private function pick(array $values)
    {
        return $values[mt_rand(0, count($values) - 1)];
    }

    /**
     * @template T
     *
     * @param  list<T>  $values
     * @return list<T>
     */
    private function pickMany(array $values, int $count): array
    {
        $keys = (array) array_rand($values, min($count, count($values)));

        return array_map(fn ($key) => $values[$key], $keys);
    }

    private function slug(string $value): string
    {
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT', $value) ?: $value;

        return trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($ascii)) ?? '', '-');
    }
}
