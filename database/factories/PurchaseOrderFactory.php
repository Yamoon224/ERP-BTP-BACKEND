<?php

namespace Database\Factories;

use App\Domains\Procurement\Enums\PurchaseOrderStatus;
use App\Models\Project;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PurchaseOrder> */
class PurchaseOrderFactory extends Factory
{
    protected $model = PurchaseOrder::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'reference' => 'PO-'.$this->faker->unique()->numberBetween(10000, 99999),
            'supplier_id' => Supplier::factory(),
            'project_id' => Project::factory(),
            'currency' => 'EUR',
            'status' => PurchaseOrderStatus::Open,
            'ordered_at' => now()->subDays(10)->toDateString(),
            'notes' => null,
            'created_by' => User::factory(),
        ];
    }

    public function closed(): self
    {
        return $this->state(fn (): array => ['status' => PurchaseOrderStatus::Closed]);
    }
}
