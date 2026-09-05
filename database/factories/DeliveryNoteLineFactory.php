<?php

namespace Database\Factories;

use App\Models\DeliveryNote;
use App\Models\DeliveryNoteLine;
use App\Models\PurchaseOrderLine;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<DeliveryNoteLine> */
class DeliveryNoteLineFactory extends Factory
{
    protected $model = DeliveryNoteLine::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'delivery_note_id' => DeliveryNote::factory(),
            'purchase_order_line_id' => PurchaseOrderLine::factory(),
            'quantity_received' => 50,
        ];
    }
}
