<?php

namespace App\Domains\Matching\Enums;

/**
 * « Qui (ou quoi) a déterminé le rapprochement » — règle fonctionnelle n°5.
 * Un rapprochement est soit produit automatiquement par le moteur (`system`),
 * soit déclenché/arbitré par un utilisateur identifié (`user`).
 */
enum ActorType: string
{
    case System = 'system';
    case User = 'user';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
