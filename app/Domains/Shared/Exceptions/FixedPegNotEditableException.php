<?php

namespace App\Domains\Shared\Exceptions;

/**
 * Une parite fixe n'est pas une cotation : le franc CFA est arrime a l'euro a
 * 1 EUR = 655,957 XOF par un texte reglementaire, pas par un marche. La
 * modifier depuis un ecran d'administration reviendrait a laisser une saisie
 * humaine reecrire une donnee de droit — et a fausser silencieusement tous les
 * rapprochements passes qui s'y referent.
 */
final class FixedPegNotEditableException extends DomainException
{
    public static function make(string $pair): self
    {
        return new self(
            message: "Le taux {$pair} est une parite fixe reglementaire : il ne peut etre ni modifie ni supprime.",
            errorCode: 'fixed_peg_not_editable',
            statusCode: 409,
            context: ['pair' => $pair],
        );
    }
}
