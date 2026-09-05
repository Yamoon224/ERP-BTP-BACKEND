<?php

namespace Database\Factories;

use App\Domains\Receiving\Enums\DeliveryNoteStatus;
use App\Models\DeliveryNote;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<DeliveryNote> */
class DeliveryNoteFactory extends Factory
{
    protected $model = DeliveryNote::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'reference' => 'BL-'.$this->faker->unique()->numberBetween(10000, 99999),
            'purchase_order_id' => PurchaseOrder::factory(),
            'supplier_id' => Supplier::factory(),
            'status' => DeliveryNoteStatus::Draft,
            'received_at' => now()->subDays(5)->toDateString(),
            'received_by' => User::factory(),
            'notes' => null,
        ];
    }

    public function accepted(): self
    {
        return $this->state(fn (): array => ['status' => DeliveryNoteStatus::Accepted]);
    }

    public function rejected(): self
    {
        return $this->state(fn (): array => ['status' => DeliveryNoteStatus::Rejected]);
    }
}
