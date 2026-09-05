<?php

namespace Database\Factories;

use App\Models\Invoice;
use App\Models\InvoiceLine;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<InvoiceLine> */
class InvoiceLineFactory extends Factory
{
    protected $model = InvoiceLine::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'invoice_id' => Invoice::factory(),
            'purchase_order_line_id' => null,
            'line_number' => 1,
            'description' => $this->faker->words(3, true),
            'quantity' => 10,
            'unit_price' => 10,
        ];
    }
}
