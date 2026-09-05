<?php

namespace App\Domains\Shared\DTOs;

use App\Domains\Shared\Enums\Currency;

/**
 * Resultat d une conversion : le montant obtenu, et le taux qui l explique.
 * Les deux voyagent ensemble, pour qu un montant converti ne circule jamais
 * sans sa justification.
 */
final readonly class ConvertedAmount
{
    public function __construct(
        public float $originalAmount,
        public float $amount,
        public Currency $currency,
        public ExchangeRate $rate,
    ) {}

    /** Aucune conversion n a eu lieu (meme devise). */
    public function isUnchanged(): bool
    {
        return $this->rate->isIdentity();
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'original_amount' => $this->originalAmount,
            'amount' => $this->amount,
            'currency' => $this->currency->value,
            'rate' => $this->rate->toArray(),
        ];
    }
}
