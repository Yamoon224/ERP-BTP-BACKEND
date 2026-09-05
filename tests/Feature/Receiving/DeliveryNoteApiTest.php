<?php

namespace Tests\Feature\Receiving;

use App\Domains\Procurement\Enums\PurchaseOrderStatus;
use App\Domains\Receiving\Enums\DeliveryNoteStatus;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Concerns\BuildsProcurementScenario;
use Tests\TestCase;

class DeliveryNoteApiTest extends TestCase
{
    use BuildsProcurementScenario, RefreshDatabase;

    /** @return array<string, mixed> */
    private function payload(float $quantity = 100, string $reference = 'BL-2026-9001'): array
    {
        return [
            'reference' => $reference,
            'purchase_order_id' => $this->purchaseOrder->id,
            'received_at' => now()->toDateString(),
            'lines' => [[
                'purchase_order_line_id' => $this->purchaseOrderLines[0]->id,
                'quantity_received' => $quantity,
            ]],
        ];
    }

    #[Test]
    public function a_delivery_note_is_created_as_a_draft_and_inherits_the_purchase_order_supplier(): void
    {
        $this->actingAsRole('warehouse');
        $this->givenPurchaseOrder();

        $this->postJson('/api/delivery-notes', $this->payload())
            ->assertCreated()
            // Saisi n'est pas contrôlé : un BL neuf ne compte pas encore comme
            // marchandise reçue.
            ->assertJsonPath('data.status', DeliveryNoteStatus::Draft->value)
            ->assertJsonPath('data.counts_as_received', false)
            ->assertJsonPath('data.supplier.id', $this->supplier->id);
    }

    #[Test]
    public function accepting_a_delivery_note_makes_it_count_and_advances_the_purchase_order(): void
    {
        $this->actingAsRole('warehouse');
        $this->givenPurchaseOrder([['quantity' => 100, 'price' => 10]]);

        $id = $this->postJson('/api/delivery-notes', $this->payload(quantity: 40))->json('data.id');

        $this->postJson("/api/delivery-notes/{$id}/review", ['status' => 'accepted'])
            ->assertOk()
            ->assertJsonPath('data.status', DeliveryNoteStatus::Accepted->value)
            ->assertJsonPath('data.counts_as_received', true);

        $this->assertDatabaseHas('purchase_orders', [
            'id' => $this->purchaseOrder->id,
            'status' => PurchaseOrderStatus::PartiallyReceived->value,
        ]);
    }

    #[Test]
    public function a_fully_received_purchase_order_is_marked_as_such(): void
    {
        $this->actingAsRole('warehouse');
        $this->givenPurchaseOrder([['quantity' => 100, 'price' => 10]]);

        $id = $this->postJson('/api/delivery-notes', $this->payload(quantity: 100))->json('data.id');
        $this->postJson("/api/delivery-notes/{$id}/review", ['status' => 'accepted'])->assertOk();

        $this->assertDatabaseHas('purchase_orders', [
            'id' => $this->purchaseOrder->id,
            'status' => PurchaseOrderStatus::FullyReceived->value,
        ]);
    }

    #[Test]
    public function a_delivery_note_cannot_be_reviewed_twice(): void
    {
        $this->actingAsRole('warehouse');
        $this->givenPurchaseOrder();

        $id = $this->postJson('/api/delivery-notes', $this->payload())->json('data.id');
        $this->postJson("/api/delivery-notes/{$id}/review", ['status' => 'accepted'])->assertOk();

        $this->postJson("/api/delivery-notes/{$id}/review", ['status' => 'rejected'])
            ->assertStatus(409)
            ->assertJsonPath('error_code', 'delivery_note_already_reviewed');
    }

    #[Test]
    public function a_delivery_note_cannot_reference_a_line_from_another_purchase_order(): void
    {
        $this->actingAsRole('warehouse');
        $this->givenPurchaseOrder();
        $foreignLine = $this->purchaseOrderLines[0];

        $this->givenPurchaseOrder([['quantity' => 50, 'price' => 20]]);

        $payload = $this->payload();
        $payload['lines'][0]['purchase_order_line_id'] = $foreignLine->id;

        $this->postJson('/api/delivery-notes', $payload)
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'delivery_line_not_on_purchase_order');
    }

    #[Test]
    public function a_delivery_note_cannot_be_recorded_against_a_closed_purchase_order(): void
    {
        $this->actingAsRole('warehouse');
        $this->givenPurchaseOrder();
        $this->purchaseOrder->update(['status' => PurchaseOrderStatus::Closed]);

        $this->postJson('/api/delivery-notes', $this->payload())
            ->assertStatus(409)
            ->assertJsonPath('error_code', 'purchase_order_not_open');
    }

    #[Test]
    public function the_same_delivery_note_reference_cannot_be_recorded_twice_for_a_supplier(): void
    {
        $this->actingAsRole('warehouse');
        $this->givenPurchaseOrder();

        $this->postJson('/api/delivery-notes', $this->payload(reference: 'BL-DUP'))->assertCreated();

        // La contrainte d'unicité en base est la seule barrière fiable contre
        // une double soumission concurrente : on vérifie qu'elle est bien là.
        $this->expectException(QueryException::class);
        $this->withoutExceptionHandling()
            ->postJson('/api/delivery-notes', $this->payload(reference: 'BL-DUP'));
    }

    #[Test]
    public function a_review_only_accepts_accepted_or_rejected(): void
    {
        $this->actingAsRole('warehouse');
        $this->givenPurchaseOrder();
        $id = $this->postJson('/api/delivery-notes', $this->payload())->json('data.id');

        $this->postJson("/api/delivery-notes/{$id}/review", ['status' => 'draft'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('status');
    }
}
