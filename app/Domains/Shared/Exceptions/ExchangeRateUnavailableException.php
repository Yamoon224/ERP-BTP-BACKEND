<?php

namespace App\Domains\Shared\Exceptions;

use App\Domains\Shared\Enums\Currency;

/**
 * Aucun taux connu pour convertir une devise vers une autre.
 *
 * Volontairement une erreur, et non un repli sur un taux par defaut : deviner
 * un taux de change sur un controle anti-fraude reviendrait a inventer le
 * montant qu on s apprete a autoriser.
 */
final class ExchangeRateUnavailableException extends DomainException
{
    public static function forPair(Currency $from, Currency $to, ?string $on = null): self
    {
        $date = $on !== null ? " au {$on}" : '';

        return new self(
            message: "Aucun taux de change connu pour convertir {$from->value} vers {$to->value}{$date}.",
            errorCode: 'exchange_rate_unavailable',
            statusCode: 422,
            context: ['from' => $from->value, 'to' => $to->value, 'on' => $on],
        );
    }
}
