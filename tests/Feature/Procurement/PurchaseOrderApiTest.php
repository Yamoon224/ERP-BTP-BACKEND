<?php

namespace Tests\Feature\Procurement;

use App\Domains\Procurement\Enums\PurchaseOrderStatus;
use App\Models\Project;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PurchaseOrderApiTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'reference' => 'PO-2026-9001',
            'supplier_id' => Supplier::factory()->create()->id,
            'project_id' => Project::factory()->create()->id,
            'currency' => 'EUR',
            'ordered_at' => now()->toDateString(),
            'lines' => [
                [
                    'item_code' => 'CIM-42',
                    'description' => 'Ciment CEM II 42,5',
                    'unit' => 'sac',
                    'quantity_ordered' => 400,
                    'unit_price' => 8.90,
                ],
                [
                    'item_code' => 'SAB-01',
                    'description' => 'Sable 0/4',
                    'unit' => 't',
                    'quantity_ordered' => 60,
                    'unit_price' => 24.50,
                ],
            ],
        ], $overrides);
    }

    #[Test]
    public function a_buyer_can_create_a_purchase_order_with_its_lines(): void
    {
        $this->actingAsRole('buyer');

        $this->postJson('/api/purchase-orders', $this->payload())
            ->assertCreated()
            ->assertJsonPath('data.reference', 'PO-2026-9001')
            ->assertJsonPath('data.status', PurchaseOrderStatus::Open->value)
            ->assertJsonPath('data.total_amount', 5030)
            ->assertJsonCount(2, 'data.lines')
            // Les lignes sont numérotées dans l'ordre de saisie : ce numéro sert
            // ensuite de repère stable dans les messages d'écart.
            ->assertJsonPath('data.lines.0.line_number', 1)
            ->assertJsonPath('data.lines.1.line_number', 2);
    }

    #[Test]
    public function it_rejects_a_purchase_order_without_lines(): void
    {
        $this->actingAsRole('buyer');

        $this->postJson('/api/purchase-orders', $this->payload(['lines' => []]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('lines');
    }

    #[Test]
    public function it_rejects_a_zero_or_negative_unit_price(): void
    {
        $this->actingAsRole('buyer');
        $payload = $this->payload();
        $payload['lines'][0]['unit_price'] = 0;

        $this->postJson('/api/purchase-orders', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors('lines.0.unit_price');
    }

    #[Test]
    public function it_rejects_a_duplicate_reference(): void
    {
        $this->actingAsRole('buyer');

        $this->postJson('/api/purchase-orders', $this->payload())->assertCreated();
        $this->postJson('/api/purchase-orders', $this->payload())
            ->assertStatus(422)
            ->assertJsonValidationErrors('reference');
    }

    #[Test]
    public function the_list_is_paginated_and_filterable(): void
    {
        $this->actingAsRole('buyer');
        $supplier = Supplier::factory()->create();
        PurchaseOrder::factory()->count(3)->create(['supplier_id' => $supplier->id]);
        PurchaseOrder::factory()->count(2)->create();

        $this->getJson('/api/purchase-orders?per_page=2')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.total', 5)
            ->assertJsonPath('meta.per_page', 2);

        $this->getJson("/api/purchase-orders?supplier_id={$supplier->id}")
            ->assertOk()
            ->assertJsonCount(3, 'data');
    }

    #[Test]
    public function an_unknown_purchase_order_returns_a_clean_404(): void
    {
        $this->actingAsRole('buyer');

        $this->getJson('/api/purchase-orders/999999')
            ->assertStatus(404)
            ->assertJsonPath('error_code', 'not_found');
    }
}
