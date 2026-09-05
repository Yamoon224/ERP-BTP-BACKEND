<?php

namespace Database\Factories;

use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PurchaseOrderLine> */
class PurchaseOrderLineFactory extends Factory
{
    protected $model = PurchaseOrderLine::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'purchase_order_id' => PurchaseOrder::factory(),
            'line_number' => 1,
            'item_code' => 'ART-'.$this->faker->numberBetween(100, 999),
            'description' => $this->faker->words(3, true),
            'unit' => 'u',
            'quantity_ordered' => 100,
            'unit_price' => 10,
        ];
    }
}
