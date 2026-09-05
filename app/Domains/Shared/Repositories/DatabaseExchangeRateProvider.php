<?php

namespace App\Domains\Shared\Repositories;

use App\Domains\Shared\Contracts\ExchangeRateProviderContract;
use App\Domains\Shared\DTOs\ExchangeRate as ExchangeRateDto;
use App\Domains\Shared\Enums\Currency;
use App\Domains\Shared\Enums\ExchangeRateSource;
use App\Domains\Shared\Exceptions\ExchangeRateUnavailableException;
use App\Models\ExchangeRate;

/**
 * Résout un taux depuis la table historisée, en trois tentatives successives.
 *
 * Ne stocker qu'un sens par paire (EUR→USD, EUR→XOF) évite d'avoir à maintenir
 * la cohérence entre un taux et son inverse — deux lignes qui divergent d'un
 * millième produiraient des rapprochements différents selon le sens de lecture.
 * L'inversion et la triangulation sont donc calculées, pas saisies.
 */
final class DatabaseExchangeRateProvider implements ExchangeRateProviderContract
{
    /**
     * Devise pivot pour la triangulation. USD→XOF n'est pas coté en direct :
     * il se déduit de USD→EUR puis EUR→XOF.
     */
    private const PIVOT = Currency::EUR;

    public function rateFor(Currency $from, Currency $to, ?string $on = null): ExchangeRateDto
    {
        $rate = $this->resolve($from, $to, $on ?? now()->toDateString());

        if ($rate === null) {
            throw ExchangeRateUnavailableException::forPair($from, $to, $on);
        }

        return $rate;
    }

    public function hasRateFor(Currency $from, Currency $to, ?string $on = null): bool
    {
        return $this->resolve($from, $to, $on ?? now()->toDateString()) !== null;
    }

    private function resolve(Currency $from, Currency $to, string $on): ?ExchangeRateDto
    {
        // 1. Identité — aucune conversion.
        if ($from === $to) {
            return ExchangeRateDto::identity($from);
        }

        // 2. Cotation directe.
        $direct = $this->lookup($from, $to, $on);
        if ($direct !== null) {
            return new ExchangeRateDto(
                from: $from,
                to: $to,
                rate: (float) $direct->rate,
                source: $direct->source,
                effectiveFrom: $direct->effective_from->toDateString(),
            );
        }

        // 3. Cotation inverse.
        $inverse = $this->lookup($to, $from, $on);
        if ($inverse !== null && (float) $inverse->rate != 0.0) {
            return new ExchangeRateDto(
                from: $from,
                to: $to,
                rate: 1 / (float) $inverse->rate,
                source: $inverse->source,
                effectiveFrom: $inverse->effective_from->toDateString(),
            );
        }

        // 4. Triangulation par la devise pivot.
        if ($from !== self::PIVOT && $to !== self::PIVOT) {
            $toPivot = $this->resolve($from, self::PIVOT, $on);
            $fromPivot = $this->resolve(self::PIVOT, $to, $on);

            if ($toPivot !== null && $fromPivot !== null) {
                return new ExchangeRateDto(
                    from: $from,
                    to: $to,
                    rate: $toPivot->rate * $fromPivot->rate,
                    // Une triangulation ne vaut que par son maillon le plus
                    // faible : si l'un des deux taux est une simple saisie, le
                    // résultat n'est pas une parité fixe.
                    source: $this->weakestSource($toPivot->source, $fromPivot->source),
                    effectiveFrom: $this->earliest($toPivot->effectiveFrom, $fromPivot->effectiveFrom),
                );
            }
        }

        return null;
    }

    /** Taux le plus récent dont la date d'effet précède ou égale la date demandée. */
    private function lookup(Currency $base, Currency $quote, string $on): ?ExchangeRate
    {
        return ExchangeRate::query()
            ->where('base_currency', $base)
            ->where('quote_currency', $quote)
            ->whereDate('effective_from', '<=', $on)
            ->orderByDesc('effective_from')
            ->orderByDesc('id')
            ->first();
    }

    private function weakestSource(ExchangeRateSource $left, ExchangeRateSource $right): ExchangeRateSource
    {
        $confidence = [
            ExchangeRateSource::FixedPeg->value => 3,
            ExchangeRateSource::Provider->value => 2,
            ExchangeRateSource::Manual->value => 1,
        ];

        return $confidence[$left->value] <= $confidence[$right->value] ? $left : $right;
    }

    private function earliest(?string $left, ?string $right): ?string
    {
        if ($left === null) {
            return $right;
        }
        if ($right === null) {
            return $left;
        }

        return min($left, $right);
    }
}
