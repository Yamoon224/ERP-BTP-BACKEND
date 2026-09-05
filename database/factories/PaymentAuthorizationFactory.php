<?php

namespace Database\Factories;

use App\Domains\Payments\Enums\PaymentAuthorizationStatus;
use App\Models\Invoice;
use App\Models\MatchRun;
use App\Models\PaymentAuthorization;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PaymentAuthorization> */
class PaymentAuthorizationFactory extends Factory
{
    protected $model = PaymentAuthorization::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'invoice_id' => Invoice::factory(),
            'match_run_id' => MatchRun::factory(),
            'currency' => 'EUR',
            'amount' => 0,
            'status' => PaymentAuthorizationStatus::Active,
            'authorized_at' => now(),
        ];
    }
}
