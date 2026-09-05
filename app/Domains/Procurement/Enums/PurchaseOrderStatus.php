<?php

namespace App\Domains\Procurement\Enums;

/**
 * Cycle de vie d'un bon de commande. Seul un PO `open` (ou `partially_received`)
 * peut recevoir de nouvelles livraisons ; un PO `closed` ou `cancelled` n'ouvre
 * plus aucun droit à paiement.
 */
enum PurchaseOrderStatus: string
{
    case Draft = 'draft';
    case Open = 'open';
    case PartiallyReceived = 'partially_received';
    case FullyReceived = 'fully_received';
    case Closed = 'closed';
    case Cancelled = 'cancelled';

    /** Un PO dans cet état accepte-t-il encore livraisons et factures ? */
    public function acceptsDocuments(): bool
    {
        return in_array($this, [self::Open, self::PartiallyReceived, self::FullyReceived], true);
    }

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Brouillon',
            self::Open => 'Ouvert',
            self::PartiallyReceived => 'Partiellement livré',
            self::FullyReceived => 'Intégralement livré',
            self::Closed => 'Clôturé',
            self::Cancelled => 'Annulé',
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
