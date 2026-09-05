<?php

namespace App\Domains\Payments\Enums;

/**
 * Une autorisation est `active` tant qu'elle reflète le dernier rapprochement
 * connu. Un nouveau rapprochement de la même facture la remplace : l'ancienne
 * passe en `superseded` et reste en base — on ne supprime jamais une trace
 * d'autorisation de paiement.
 */
enum PaymentAuthorizationStatus: string
{
    case Active = 'active';
    case Superseded = 'superseded';
    case Revoked = 'revoked';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Superseded => 'Remplacée',
            self::Revoked => 'Révoquée',
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
