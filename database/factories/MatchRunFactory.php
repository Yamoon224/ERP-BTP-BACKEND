<?php

namespace Database\Factories;

use App\Domains\Matching\Enums\ActorType;
use App\Domains\Matching\Enums\MatchStatus;
use App\Models\Invoice;
use App\Models\MatchRun;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<MatchRun> */
class MatchRunFactory extends Factory
{
    protected $model = MatchRun::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'invoice_id' => Invoice::factory(),
            'actor_type' => ActorType::System,
            'actor_id' => null,
            'trigger' => 'manual',
            'engine_version' => '1.0.0',
            'tolerance_snapshot' => [
                'price_ratio' => 0.01,
                'price_absolute' => 0.5,
                'quantity_ratio' => 0.0,
                'quantity_absolute' => 0.0,
            ],
            'status' => MatchStatus::Unmatched,
            'invoiced_amount' => 0,
            'matched_amount' => 0,
            'unmatched_amount' => 0,
            'exception_count' => 0,
            'evaluated_at' => now(),
        ];
    }
}
