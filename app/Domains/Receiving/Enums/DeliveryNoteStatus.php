<?php

namespace App\Domains\Receiving\Enums;

/**
 * Un bon de livraison ne compte comme quantité reçue — donc payable — qu'une
 * fois `accepted`. Un BL `draft` (saisi mais non contrôlé) ou `rejected`
 * (marchandise refusée) n'alimente jamais le rapprochement : c'est la parade
 * principale contre les livraisons fictives.
 */
enum DeliveryNoteStatus: string
{
    case Draft = 'draft';
    case Accepted = 'accepted';
    case Rejected = 'rejected';

    /** Ce BL alimente-t-il les quantités reçues opposables au paiement ? */
    public function countsAsReceived(): bool
    {
        return $this === self::Accepted;
    }

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Brouillon',
            self::Accepted => 'Accepté',
            self::Rejected => 'Refusé',
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
