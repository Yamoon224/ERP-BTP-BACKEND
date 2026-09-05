<?php

namespace App\Domains\Shared\Enums;

/**
 * Devises supportées par le circuit achats.
 *
 * Le nombre de décimales n'est pas cosmétique : le franc CFA n'a **pas de
 * sous-unité**. Arrondir un montant XOF à deux décimales produirait des
 * centimes qui n'existent pas, et un écart systématique entre le montant
 * autorisé et le montant réellement virable. Chaque arrondi monétaire du
 * système passe donc par `round()` ci-dessous plutôt que par un `round($x, 2)`
 * codé en dur.
 */
enum Currency: string
{
    case EUR = 'EUR';
    case USD = 'USD';
    case XOF = 'XOF';

    /** Nombre de décimales de la sous-unité (ISO 4217). */
    public function decimals(): int
    {
        return match ($this) {
            self::EUR, self::USD => 2,
            // Le franc CFA n'a pas de centime.
            self::XOF => 0,
        };
    }

    public function symbol(): string
    {
        return match ($this) {
            self::EUR => '€',
            self::USD => '$',
            self::XOF => 'F CFA',
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::EUR => 'Euro',
            self::USD => 'Dollar américain',
            self::XOF => 'Franc CFA (BCEAO)',
        };
    }

    /** Arrondit un montant à la précision réelle de la devise. */
    public function round(float $amount): float
    {
        return round($amount, $this->decimals());
    }

    /**
     * Plus petite unité monétaire représentable. Sert de plancher aux écarts
     * d'arrondi tolérés après conversion : un écart inférieur au centime (ou au
     * franc) n'est pas un écart, c'est une limite de représentation.
     */
    public function smallestUnit(): float
    {
        return 1 / (10 ** $this->decimals());
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
