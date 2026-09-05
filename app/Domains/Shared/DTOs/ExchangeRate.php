<?php

namespace App\Domains\Shared\DTOs;

use App\Domains\Shared\Enums\Currency;
use App\Domains\Shared\Enums\ExchangeRateSource;

/**
 * Un taux de change appliqué, avec de quoi le justifier plus tard.
 *
 * Immuable et sérialisable : une copie est archivée dans chaque exécution du
 * moteur. Sans elle, un montant autorisé sur une facture en dollars deviendrait
 * inexplicable dès le lendemain — on saurait *combien* a été autorisé, pas
 * *pourquoi ce montant-là*.
 */
final readonly class ExchangeRate
{
    public function __construct(
        public Currency $from,
        public Currency $to,
        /** Multiplicateur : montant_en_`to` = montant_en_`from` × rate. */
        public float $rate,
        public ExchangeRateSource $source,
        /** Date d'effet du taux retenu (et non la date d'exécution du moteur). */
        public ?string $effectiveFrom = null,
    ) {}

    /** Taux neutre : aucune conversion n'a lieu. */
    public static function identity(Currency $currency): self
    {
        return new self($currency, $currency, 1.0, ExchangeRateSource::FixedPeg);
    }

    public function isIdentity(): bool
    {
        return $this->from === $this->to;
    }

    public function convert(float $amount): float
    {
        return $amount * $this->rate;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'from' => $this->from->value,
            'to' => $this->to->value,
            'rate' => $this->rate,
            'source' => $this->source->value,
            'effective_from' => $this->effectiveFrom,
        ];
    }
}
