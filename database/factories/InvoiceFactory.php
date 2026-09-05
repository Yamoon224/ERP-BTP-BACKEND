<?php

namespace Database\Factories;

use App\Domains\Invoicing\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Invoice> */
class InvoiceFactory extends Factory
{
    protected $model = Invoice::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'reference' => 'FAC-'.$this->faker->unique()->numberBetween(10000, 99999),
            'supplier_id' => Supplier::factory(),
            'purchase_order_id' => PurchaseOrder::factory(),
            'currency' => 'EUR',
            'status' => InvoiceStatus::Received,
            'invoice_date' => now()->subDays(2)->toDateString(),
            'due_date' => now()->addDays(28)->toDateString(),
            'total_amount' => 0,
            'created_by' => User::factory(),
        ];
    }
}
