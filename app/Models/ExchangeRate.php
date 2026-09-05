<?php

namespace App\Models;

use App\Domains\Shared\Enums\Currency;
use App\Domains\Shared\Enums\ExchangeRateSource;
use Database\Factories\ExchangeRateFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property Currency $base_currency
 * @property Currency $quote_currency
 * @property numeric-string $rate
 * @property ExchangeRateSource $source
 * @property Carbon $effective_from
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class ExchangeRate extends Model
{
    /** @use HasFactory<ExchangeRateFactory> */
    use HasFactory;

    protected $fillable = ['base_currency', 'quote_currency', 'rate', 'source', 'effective_from'];

    protected function casts(): array
    {
        return [
            'base_currency' => Currency::class,
            'quote_currency' => Currency::class,
            'source' => ExchangeRateSource::class,
            'effective_from' => 'date',
            'rate' => 'decimal:10',
        ];
    }
}
