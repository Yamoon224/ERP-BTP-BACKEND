<?php

namespace Database\Seeders;

use App\Domains\Invoicing\Services\InvoiceService;
use App\Domains\Procurement\Services\PurchaseOrderService;
use App\Domains\Receiving\Enums\DeliveryNoteStatus;
use App\Domains\Receiving\Services\DeliveryNoteService;
use App\Models\Project;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Jeu de démonstration couvrant les quatre situations que le moteur doit
 * savoir distinguer. Les documents sont créés via les services applicatifs et
 * non en base directement : le jeu de données est donc produit par le même
 * chemin de code que la production, rapprochements et pistes d'audit compris.
 *
 *  1. Rapprochement complet    → paiement intégralement autorisé
 *  2. Livraison partielle      → paiement partiel, reste en attente de BL
 *  3. Écart de prix            → écart signalé, aucun paiement sur la ligne
 *  4. Sur-facturation          → portion saine payée, excédent en revue
 *  5. Facture en dollars       → conversion au taux du jour, paiement en USD
 *  6. Facture en francs CFA    → conversion à la parité fixe, paiement en XOF
 */
class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $users = $this->createUsers();
        $supplier = Supplier::create([
            'code' => 'SUP-BETON',
            'name' => 'Béton Express SAS',
            'vat_number' => 'FR12345678901',
            'email' => 'facturation@beton-express.example',
            'is_active' => true,
        ]);
        $project = Project::create([
            'code' => 'CH-A12',
            'name' => 'Chantier A12 — Viaduc Nord',
            'client_name' => 'Conseil Départemental',
            'is_active' => true,
        ]);

        $this->scenarioFullyMatched($users, $supplier, $project);
        $this->scenarioPartialDelivery($users, $supplier, $project);
        $this->scenarioPriceVariance($users, $supplier, $project);
        $this->scenarioOverInvoiced($users, $supplier, $project);

        // Les deux scenarios multidevises utilisent des fournisseurs dedies :
        // un fournisseur facture dans SA devise, pas dans celle de l'ERP.
        $this->scenarioDollarInvoice($users, $project);
        $this->scenarioCfaFrancInvoice($users, $project);
    }

    /** @return array<string, User> */
    private function createUsers(): array
    {
        $definitions = [
            'admin' => ['Awa Diop', 'admin@erp.test'],
            'buyer' => ['Marc Lemoine', 'acheteur@erp.test'],
            'warehouse' => ['Sofia Ferreira', 'magasinier@erp.test'],
            'accountant' => ['Julien Bardot', 'comptable@erp.test'],
            'controller' => ['Nadia Belkacem', 'controleur@erp.test'],
        ];

        $users = [];

        foreach ($definitions as $role => [$name, $email]) {
            $user = User::firstOrCreate(
                ['email' => $email],
                ['name' => $name, 'password' => Hash::make('password')],
            );
            $user->syncRoles([$role]);
            $users[$role] = $user;
        }

        return $users;
    }

    /** Cas nominal : commandé, reçu et facturé à l'identique.
     *
     * @param  array<string, User>  $users
     */
    private function scenarioFullyMatched(array $users, Supplier $supplier, Project $project): void
    {
        $purchaseOrder = $this->createPurchaseOrder($users['buyer'], $supplier, $project, 'PO-2026-0001', [
            $this->line('CIM-42', 'Ciment CEM II 42,5 — sac 35 kg', 'sac', 400, 8.90),
            $this->line('SAB-01', 'Sable 0/4 lavé', 't', 60, 24.50),
        ]);

        $this->deliver($users['warehouse'], $purchaseOrder, 'BL-2026-0001', [400, 60], accept: true);

        $this->invoice($users['accountant'], $purchaseOrder, 'FAC-2026-0001', [
            [0, 400, 8.90],
            [1, 60, 24.50],
        ]);
    }

    /** Facturation complète mais livraison partielle : seul le reçu est payable.
     *
     * @param  array<string, User>  $users
     */
    private function scenarioPartialDelivery(array $users, Supplier $supplier, Project $project): void
    {
        $purchaseOrder = $this->createPurchaseOrder($users['buyer'], $supplier, $project, 'PO-2026-0002', [
            $this->line('ACI-HA12', 'Acier HA12 — barre 12 m', 'u', 200, 18.40),
        ]);

        // 120 barres reçues sur 200 facturées.
        $this->deliver($users['warehouse'], $purchaseOrder, 'BL-2026-0002', [120], accept: true);

        $this->invoice($users['accountant'], $purchaseOrder, 'FAC-2026-0002', [
            [0, 200, 18.40],
        ]);
    }

    /** Prix unitaire gonflé au-delà de la tolérance : arbitrage humain requis.
     *
     * @param  array<string, User>  $users
     */
    private function scenarioPriceVariance(array $users, Supplier $supplier, Project $project): void
    {
        $purchaseOrder = $this->createPurchaseOrder($users['buyer'], $supplier, $project, 'PO-2026-0003', [
            $this->line('LOC-PEL', 'Location pelle 20 t — journée', 'j', 15, 540.00),
        ]);

        $this->deliver($users['warehouse'], $purchaseOrder, 'BL-2026-0003', [15], accept: true);

        // 612 EUR au lieu de 540 : +13,3 %, très au-delà de la tolérance de 1 %.
        $this->invoice($users['accountant'], $purchaseOrder, 'FAC-2026-0003', [
            [0, 15, 612.00],
        ]);
    }

    /** Quantité facturée supérieure au commandé : la portion saine reste payable.
     *
     * @param  array<string, User>  $users
     */
    private function scenarioOverInvoiced(array $users, Supplier $supplier, Project $project): void
    {
        $purchaseOrder = $this->createPurchaseOrder($users['buyer'], $supplier, $project, 'PO-2026-0004', [
            $this->line('PAR-BET', 'Parpaing béton 20x20x50', 'u', 1000, 1.35),
        ]);

        $this->deliver($users['warehouse'], $purchaseOrder, 'BL-2026-0004', [1000], accept: true);

        // 1 150 facturés pour 1 000 commandés et reçus.
        $this->invoice($users['accountant'], $purchaseOrder, 'FAC-2026-0004', [
            [0, 1150, 1.35],
        ]);
    }

    /**
     * Fournisseur americain : bon de commande en euros (reference
     * contractuelle), facture en dollars. Le moteur convertit au taux du jour
     * de la facture pour comparer les prix, et autorise le paiement en dollars.
     *
     * @param  array<string, User>  $users
     */
    private function scenarioDollarInvoice(array $users, Project $project): void
    {
        $supplier = Supplier::create([
            'code' => 'SUP-USTOOL',
            'name' => 'US Heavy Tools Inc.',
            'vat_number' => null,
            'email' => 'ar@usheavytools.example',
            'is_active' => true,
        ]);

        $purchaseOrder = $this->createPurchaseOrder($users['buyer'], $supplier, $project, 'PO-2026-0005', [
            // 12 000 EUR l'unite au bon de commande.
            $this->line('COF-3T', 'Coffrage metallique 3 t', 'u', 4, 12000.00),
        ], currency: 'EUR');

        $this->deliver($users['warehouse'], $purchaseOrder, 'BL-2026-0005', [4], accept: true);

        // 13 020 USD l'unite : au taux de 1 EUR = 1,085 USD, cela fait
        // exactement 12 000 EUR. Le rapprochement doit etre complet.
        $this->invoice($users['accountant'], $purchaseOrder, 'FAC-2026-0005', [
            [0, 4, 13020.00],
        ], currency: 'USD');
    }

    /**
     * Fournisseur ouest-africain : bon de commande ET facture en francs CFA,
     * mais pilotage en euros. La parite est fixe (1 EUR = 655,957 XOF), et le
     * franc CFA n'a pas de centime.
     *
     * @param  array<string, User>  $users
     */
    private function scenarioCfaFrancInvoice(array $users, Project $project): void
    {
        $supplier = Supplier::create([
            'code' => 'SUP-SAHEL',
            'name' => 'Sahel Materiaux SARL',
            'vat_number' => null,
            'email' => 'facturation@sahel-materiaux.example',
            'is_active' => true,
        ]);

        $purchaseOrder = $this->createPurchaseOrder($users['buyer'], $supplier, $project, 'PO-2026-0006', [
            $this->line('GRA-1020', 'Gravier 10/20 concasse', 't', 250, 22000),
        ], currency: 'XOF');

        // 180 t recues sur 250 commandees : livraison partielle, en devise
        // locale, pour verifier que plafonnement et conversion se combinent.
        $this->deliver($users['warehouse'], $purchaseOrder, 'BL-2026-0006', [180], accept: true);

        $this->invoice($users['accountant'], $purchaseOrder, 'FAC-2026-0006', [
            [0, 250, 22000],
        ], currency: 'XOF');
    }

    /** @param  list<array<string, mixed>>  $lines */
    private function createPurchaseOrder(
        User $buyer,
        Supplier $supplier,
        Project $project,
        string $reference,
        array $lines,
        string $currency = 'EUR',
    ): PurchaseOrder {
        return app(PurchaseOrderService::class)->create([
            'reference' => $reference,
            'supplier_id' => $supplier->id,
            'project_id' => $project->id,
            'currency' => $currency,
            'ordered_at' => now()->subDays(20)->toDateString(),
            'notes' => null,
            'lines' => $lines,
        ], $buyer);
    }

    /** @return array<string, mixed> */
    private function line(string $code, string $description, string $unit, float $quantity, float $price): array
    {
        return [
            'item_code' => $code,
            'description' => $description,
            'unit' => $unit,
            'quantity_ordered' => $quantity,
            'unit_price' => $price,
        ];
    }

    /** @param  list<float>  $quantities  quantité reçue, dans l'ordre des lignes du PO */
    private function deliver(
        User $receiver,
        PurchaseOrder $purchaseOrder,
        string $reference,
        array $quantities,
        bool $accept,
    ): void {
        $orderLines = $purchaseOrder->lines()->orderBy('line_number')->get();

        $deliveryNote = app(DeliveryNoteService::class)->record([
            'reference' => $reference,
            'purchase_order_id' => $purchaseOrder->id,
            'received_at' => now()->subDays(10)->toDateString(),
            'lines' => array_map(
                fn (float $quantity, int $index): array => [
                    'purchase_order_line_id' => $orderLines[$index]->id,
                    'quantity_received' => $quantity,
                ],
                $quantities,
                array_keys($quantities),
            ),
        ], $receiver);

        if ($accept) {
            app(DeliveryNoteService::class)->review($deliveryNote, DeliveryNoteStatus::Accepted, $receiver);
        }
    }

    /** @param  list<array{0: int, 1: float, 2: float}>  $lines  [index de ligne PO, quantité, prix unitaire] */
    private function invoice(
        User $accountant,
        PurchaseOrder $purchaseOrder,
        string $reference,
        array $lines,
        string $currency = 'EUR',
    ): void {
        $orderLines = $purchaseOrder->lines()->orderBy('line_number')->get();

        app(InvoiceService::class)->submit([
            'reference' => $reference,
            'purchase_order_id' => $purchaseOrder->id,
            'currency' => $currency,
            'invoice_date' => now()->subDays(3)->toDateString(),
            'due_date' => now()->addDays(27)->toDateString(),
            'lines' => array_map(
                fn (array $line): array => [
                    'purchase_order_line_id' => $orderLines[$line[0]]->id,
                    'description' => $orderLines[$line[0]]->description,
                    'quantity' => $line[1],
                    'unit_price' => $line[2],
                ],
                $lines,
            ),
        ], $accountant);
    }
}
