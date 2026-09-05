<?php

namespace Database\Factories;

use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Supplier> */
class SupplierFactory extends Factory
{
    protected $model = Supplier::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'code' => 'SUP-'.$this->faker->unique()->numberBetween(1000, 9999),
            'name' => $this->faker->company(),
            'vat_number' => 'FR'.$this->faker->numberBetween(10000000000, 99999999999),
            'email' => $this->faker->companyEmail(),
            'is_active' => true,
        ];
    }
}
