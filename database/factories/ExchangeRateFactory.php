<?php

namespace Database\Factories;

use App\Domains\Shared\Enums\Currency;
use App\Domains\Shared\Enums\ExchangeRateSource;
use App\Models\ExchangeRate;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ExchangeRate> */
class ExchangeRateFactory extends Factory
{
    protected $model = ExchangeRate::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'base_currency' => Currency::EUR,
            'quote_currency' => Currency::USD,
            'rate' => 1.08,
            'source' => ExchangeRateSource::Manual,
            'effective_from' => now()->subYear()->toDateString(),
        ];
    }
}
