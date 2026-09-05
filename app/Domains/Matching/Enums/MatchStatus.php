<?php

namespace App\Domains\Matching\Enums;

/**
 * Résultat d'un rapprochement, au niveau d'une ligne comme au niveau d'une
 * facture entière.
 *
 * `Exception` est délibérément distinct de `Unmatched` : « non rapproché » est
 * un état normal et transitoire (la marchandise n'est pas encore arrivée),
 * tandis qu'« exception » signifie qu'un écart a été détecté et attend une
 * décision humaine (règle fonctionnelle n°6 : ni acceptation ni rejet
 * silencieux).
 */
enum MatchStatus: string
{
    case Matched = 'matched';
    case PartiallyMatched = 'partially_matched';
    case Unmatched = 'unmatched';
    case Exception = 'exception';

    /** Cet état autorise-t-il un paiement (total ou partiel) ? */
    public function allowsPayment(): bool
    {
        return in_array($this, [self::Matched, self::PartiallyMatched], true);
    }

    /** Cet état requiert-il une revue humaine ? */
    public function requiresReview(): bool
    {
        return $this === self::Exception;
    }

    public function label(): string
    {
        return match ($this) {
            self::Matched => 'Rapproché',
            self::PartiallyMatched => 'Partiellement rapproché',
            self::Unmatched => 'Non rapproché',
            self::Exception => 'Écart à arbitrer',
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
