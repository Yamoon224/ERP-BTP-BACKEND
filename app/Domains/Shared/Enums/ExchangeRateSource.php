<?php

namespace App\Domains\Shared\Enums;

/**
 * Provenance d'un taux de change. Tracée parce qu'elle n'engage pas la même
 * confiance : une parité fixe est une donnée réglementaire, un taux saisi à la
 * main est une décision humaine qui doit pouvoir être contestée.
 */
enum ExchangeRateSource: string
{
    /**
     * Parité fixe et réglementaire — le franc CFA est arrimé à l'euro à
     * 1 EUR = 655,957 XOF depuis 1999. Ce n'est pas un taux de marché : il ne
     * bouge pas, et le traiter comme une cotation quotidienne serait une erreur
     * de modélisation.
     */
    case FixedPeg = 'fixed_peg';

    /** Taux saisi manuellement par un utilisateur habilité. */
    case Manual = 'manual';

    /** Taux importé depuis un fournisseur de cotations externe. */
    case Provider = 'provider';

    public function label(): string
    {
        return match ($this) {
            self::FixedPeg => 'Parité fixe',
            self::Manual => 'Saisie manuelle',
            self::Provider => 'Fournisseur de cotations',
        };
    }

    /** Une parité fixe ne se périme pas ; un taux de marché, si. */
    public function expires(): bool
    {
        return $this !== self::FixedPeg;
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
