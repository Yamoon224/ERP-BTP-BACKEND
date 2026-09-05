<?php

namespace Tests\Feature\Concerns;

use App\Domains\Procurement\Enums\PurchaseOrderStatus;
use App\Domains\Receiving\Enums\DeliveryNoteStatus;
use App\Models\DeliveryNote;
use App\Models\DeliveryNoteLine;
use App\Models\Project;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\Supplier;

/**
 * Fabrique de scénarios achats pour les tests d'intégration.
 *
 * Les documents sont créés directement en base (et non via l'API) quand ils ne
 * sont que le décor du test : ce qui est testé, c'est ce qui vient après.
 */
trait BuildsProcurementScenario
{
    protected Supplier $supplier;

    protected Project $project;

    protected PurchaseOrder $purchaseOrder;

    /** @var list<PurchaseOrderLine> */
    protected array $purchaseOrderLines = [];

    /**
     * @param  list<array{quantity: float, price: float}>  $lines
     */
    protected function givenPurchaseOrder(
        array $lines = [['quantity' => 100, 'price' => 10]],
        string $currency = 'EUR',
    ): PurchaseOrder {
        $this->supplier = Supplier::factory()->create();
        $this->project = Project::factory()->create();
        $this->purchaseOrder = PurchaseOrder::factory()->create([
            'supplier_id' => $this->supplier->id,
            'project_id' => $this->project->id,
            'currency' => $currency,
            'status' => PurchaseOrderStatus::Open,
        ]);

        $this->purchaseOrderLines = [];

        foreach ($lines as $index => $line) {
            $this->purchaseOrderLines[] = PurchaseOrderLine::factory()->create([
                'purchase_order_id' => $this->purchaseOrder->id,
                'line_number' => $index + 1,
                'item_code' => 'ART-'.($index + 1),
                'quantity_ordered' => $line['quantity'],
                'unit_price' => $line['price'],
            ]);
        }

        return $this->purchaseOrder;
    }

    /**
     * Livraison réceptionnée et acceptée : les quantités deviennent opposables
     * au paiement.
     *
     * @param  list<float>  $quantities  dans l'ordre des lignes du PO
     */
    protected function givenAcceptedDelivery(array $quantities, string $reference = 'BL-TEST-1'): DeliveryNote
    {
        return $this->givenDelivery($quantities, DeliveryNoteStatus::Accepted, $reference);
    }

    /** @param  list<float>  $quantities */
    protected function givenDelivery(
        array $quantities,
        DeliveryNoteStatus $status,
        string $reference = 'BL-TEST-1',
    ): DeliveryNote {
        $deliveryNote = DeliveryNote::factory()->create([
            'reference' => $reference,
            'purchase_order_id' => $this->purchaseOrder->id,
            'supplier_id' => $this->supplier->id,
            'status' => $status,
        ]);

        foreach ($quantities as $index => $quantity) {
            DeliveryNoteLine::factory()->create([
                'delivery_note_id' => $deliveryNote->id,
                'purchase_order_line_id' => $this->purchaseOrderLines[$index]->id,
                'quantity_received' => $quantity,
            ]);
        }

        return $deliveryNote;
    }

    /**
     * Charge utile de soumission de facture, alignée sur les lignes du PO.
     *
     * @param  list<array{line: int, quantity: float, price: float}>  $lines
     * @return array<string, mixed>
     */
    protected function invoicePayload(array $lines, string $reference = 'FAC-TEST-1'): array
    {
        return [
            'reference' => $reference,
            'purchase_order_id' => $this->purchaseOrder->id,
            // Par defaut, la facture reprend la devise du bon de commande ; les
            // tests multidevises surchargent cette cle explicitement.
            'currency' => $this->purchaseOrder->currency->value,
            'invoice_date' => now()->toDateString(),
            'lines' => array_map(fn (array $line): array => [
                'purchase_order_line_id' => $this->purchaseOrderLines[$line['line']]->id,
                'description' => 'Ligne facturée',
                'quantity' => $line['quantity'],
                'unit_price' => $line['price'],
            ], $lines),
        ];
    }
}
