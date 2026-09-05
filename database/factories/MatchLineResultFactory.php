<?php

namespace Database\Factories;

use App\Domains\Matching\Enums\MatchStatus;
use App\Models\InvoiceLine;
use App\Models\MatchLineResult;
use App\Models\MatchRun;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<MatchLineResult> */
class MatchLineResultFactory extends Factory
{
    protected $model = MatchLineResult::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'match_run_id' => MatchRun::factory(),
            'invoice_line_id' => InvoiceLine::factory(),
            'purchase_order_line_id' => null,
            'status' => MatchStatus::Unmatched,
            'quantity_invoiced' => 0,
            'quantity_matched' => 0,
            'quantity_unmatched' => 0,
            'unit_price_invoiced' => 0,
            'unit_price_ordered' => null,
            'price_variance_ratio' => null,
            'matched_amount' => 0,
            'evidence' => [],
        ];
    }
}
