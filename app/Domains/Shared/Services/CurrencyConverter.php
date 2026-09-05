<?php

namespace App\Domains\Shared\Services;

use App\Domains\Shared\Contracts\ExchangeRateProviderContract;
use App\Domains\Shared\DTOs\ConvertedAmount;
use App\Domains\Shared\DTOs\ExchangeRate;
use App\Domains\Shared\Enums\Currency;
use App\Domains\Shared\Exceptions\ExchangeRateUnavailableException;

/**
 * Conversion monétaire.
 *
 * Deux règles y sont tenues, et elles comptent autant que le calcul lui-même :
 *
 *  1. **On arrondit le plus tard possible.** Les prix unitaires convertis sont
 *     conservés en pleine précision et ne sont arrondis qu'au moment de former
 *     un montant. Arrondir un prix unitaire à 2 décimales avant de le
 *     multiplier par 1 150 unités produit un écart visible sur la facture.
 *  2. **Le taux appliqué ressort avec le résultat.** Un montant converti sans
 *     son taux est un chiffre invérifiable ; sur un contrôle anti-fraude, c'est
 *     inacceptable.
 */
final class CurrencyConverter
{
    public function __construct(private readonly ExchangeRateProviderContract $rates) {}

    /**
     * Convertit un montant et arrondit à la précision réelle de la devise cible
     * (deux décimales en euro, aucune en franc CFA).
     *
     * @param  string|null  $on  Date d'effet du taux (Y-m-d)
     *
     * @throws ExchangeRateUnavailableException
     */
    public function convert(float $amount, Currency $from, Currency $to, ?string $on = null): ConvertedAmount
    {
        $rate = $this->rates->rateFor($from, $to, $on);

        return new ConvertedAmount(
            originalAmount: $amount,
            amount: $to->round($rate->convert($amount)),
            currency: $to,
            rate: $rate,
        );
    }

    /**
     * Convertit sans arrondir : réservé aux prix unitaires, qui seront
     * multipliés par une quantité avant de devenir un montant.
     *
     * @throws ExchangeRateUnavailableException
     */
    public function convertUnitPrice(float $unitPrice, Currency $from, Currency $to, ?string $on = null): ConvertedAmount
    {
        $rate = $this->rates->rateFor($from, $to, $on);

        return new ConvertedAmount(
            originalAmount: $unitPrice,
            amount: $rate->convert($unitPrice),
            currency: $to,
            rate: $rate,
        );
    }

    public function rateFor(Currency $from, Currency $to, ?string $on = null): ExchangeRate
    {
        return $this->rates->rateFor($from, $to, $on);
    }

    public function hasRateFor(Currency $from, Currency $to, ?string $on = null): bool
    {
        return $this->rates->hasRateFor($from, $to, $on);
    }
}
