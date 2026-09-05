<?php

namespace App\Domains\Shared\Support;

/**
 * Comparaisons de decimaux tolerantes a l'erreur de representation binaire.
 *
 * Les quantites (3 decimales) et les prix (4 decimales) transitent en float :
 * comparer deux float avec == ferait echouer des egalites exactes du point de
 * vue metier (0.1 + 0.2 !== 0.3). Toutes les comparaisons du moteur passent
 * donc par ici, avec un epsilon plus fin que la derniere decimale stockee.
 */
final class Decimal
{
    /** Un cran sous la precision la plus fine du schema (prix : 4 decimales). */
    public const EPSILON = 0.000005;

    public static function equals(float $left, float $right): bool
    {
        return abs($left - $right) < self::EPSILON;
    }

    public static function greaterThan(float $left, float $right): bool
    {
        return $left - $right > self::EPSILON;
    }

    public static function lessThan(float $left, float $right): bool
    {
        return $right - $left > self::EPSILON;
    }

    public static function isZero(float $value): bool
    {
        return abs($value) < self::EPSILON;
    }

    public static function isPositive(float $value): bool
    {
        return $value > self::EPSILON;
    }

    /** Quantite : 3 decimales, jamais negative. */
    public static function quantity(float $value): float
    {
        return round(max($value, 0.0), 3);
    }

    /** Montant monetaire : 2 decimales. */
    public static function money(float $value): float
    {
        return round($value, 2);
    }
}
