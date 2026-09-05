<?php

namespace App\Domains\Shared\Contracts;

use App\Domains\Shared\DTOs\ExchangeRate;
use App\Domains\Shared\Enums\Currency;
use App\Domains\Shared\Exceptions\ExchangeRateUnavailableException;

/**
 * Fournit le taux applicable entre deux devises a une date donnee.
 *
 * Abstrait derriere un contrat pour deux raisons : brancher demain un
 * fournisseur de cotations externe ne doit pas toucher au moteur, et les tests
 * doivent pouvoir fixer un taux sans passer par la base.
 */
interface ExchangeRateProviderContract
{
    /**
     * @param  string|null  $on  Date d effet souhaitee (Y-m-d). Par defaut : aujourd hui.
     *
     * @throws ExchangeRateUnavailableException si aucun taux n est connu pour la paire
     */
    public function rateFor(Currency $from, Currency $to, ?string $on = null): ExchangeRate;

    /** Un taux est-il disponible, sans lever d exception ? */
    public function hasRateFor(Currency $from, Currency $to, ?string $on = null): bool;
}
