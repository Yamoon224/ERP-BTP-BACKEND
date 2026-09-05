<?php

namespace Database\Factories;

use App\Domains\Matching\Enums\DiscrepancySeverity;
use App\Domains\Matching\Enums\DiscrepancyType;
use App\Domains\Matching\Enums\ReviewStatus;
use App\Models\Invoice;
use App\Models\MatchException;
use App\Models\MatchRun;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<MatchException> */
class MatchExceptionFactory extends Factory
{
    protected $model = MatchException::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'match_run_id' => MatchRun::factory(),
            'invoice_id' => Invoice::factory(),
            'invoice_line_id' => null,
            'match_line_result_id' => null,
            'type' => DiscrepancyType::PriceVariance,
            'severity' => DiscrepancySeverity::Medium,
            'message' => 'Ecart de prix detecte.',
            'context' => [],
            'review_status' => ReviewStatus::Open,
        ];
    }
}
