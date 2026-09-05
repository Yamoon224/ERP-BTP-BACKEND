<?php

namespace App\Domains\Shared\Http\Controllers;

use App\Domains\Shared\Enums\Currency;
use App\Domains\Shared\Enums\ExchangeRateSource;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/**
 * Referentiel des devises : ce que le circuit sait manipuler.
 *
 * Servi par l'API plutot que recopie dans le frontend pour une raison precise :
 * le nombre de decimales n'est pas cosmetique. Si l'interface arrondit le franc
 * CFA a deux decimales quand le backend l'arrondit a zero, les deux affichent
 * un montant different pour la meme autorisation de paiement. Une seule source
 * decide, et c'est celle qui calcule.
 */
class CurrencyController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'data' => [
                'currencies' => array_map(
                    fn (Currency $currency): array => [
                        'code' => $currency->value,
                        'label' => $currency->label(),
                        'symbol' => $currency->symbol(),
                        'decimals' => $currency->decimals(),
                    ],
                    Currency::cases(),
                ),
                'sources' => array_map(
                    fn (ExchangeRateSource $source): array => [
                        'value' => $source->value,
                        'label' => $source->label(),
                        'expires' => $source->expires(),
                    ],
                    ExchangeRateSource::cases(),
                ),
                // Devise proposee aux documents crees sans devise explicite.
                'default_currency' => (string) config('matching.default_currency'),
                // Devise d'agregation du pilotage et des seuils absolus.
                'base_currency' => (string) config('matching.base_currency'),
            ],
        ]);
    }
}
