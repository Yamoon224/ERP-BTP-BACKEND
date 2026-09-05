<?php

namespace Database\Seeders;

use App\Domains\Shared\Enums\Currency;
use App\Domains\Shared\Enums\ExchangeRateSource;
use App\Models\ExchangeRate;
use Illuminate\Database\Seeder;

/**
 * Taux de change de référence.
 *
 * Seul le sens EUR → X est enregistré ; l'inverse et la triangulation
 * (USD → XOF) sont calculés par le fournisseur de taux. Stocker les deux sens
 * obligerait à maintenir leur cohérence, et deux lignes qui divergent d'un
 * millième produiraient des rapprochements différents selon le sens de lecture.
 */
class ExchangeRateSeeder extends Seeder
{
    /**
     * Parité fixe et réglementaire du franc CFA : 1 EUR = 655,957 XOF depuis
     * l'arrimage à l'euro en 1999. Ce n'est pas une cotation de marché, elle ne
     * bouge pas — d'où une date d'effet ancienne et la source `fixed_peg`.
     */
    private const XOF_PEG = 655.957;

    /**
     * Taux EUR → USD. Celui-ci flotte réellement : la valeur ci-dessous n'est
     * qu'un point de départ pour la démonstration, destiné à être alimenté par
     * un import quotidien en production.
     */
    private const USD_RATE = 1.0850;

    public function run(): void
    {
        ExchangeRate::updateOrCreate(
            [
                'base_currency' => Currency::EUR,
                'quote_currency' => Currency::XOF,
                'effective_from' => '1999-01-01',
            ],
            [
                'rate' => self::XOF_PEG,
                'source' => ExchangeRateSource::FixedPeg,
            ],
        );

        ExchangeRate::updateOrCreate(
            [
                'base_currency' => Currency::EUR,
                'quote_currency' => Currency::USD,
                // Date volontairement ancienne : le jeu de démonstration crée
                // des documents datés de plusieurs semaines, et un taux qui
                // n'entre en vigueur qu'aujourd'hui ne s'y appliquerait pas.
                'effective_from' => now()->subYear()->startOfYear()->toDateString(),
            ],
            [
                'rate' => self::USD_RATE,
                'source' => ExchangeRateSource::Manual,
            ],
        );
    }
}
