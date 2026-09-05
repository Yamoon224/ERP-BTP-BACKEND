<?php

namespace App\Domains\Invoicing\Enums;

enum InvoiceStatus: string
{
    case Received = 'received';
    case UnderReview = 'under_review';
    case PartiallyApproved = 'partially_approved';
    case Approved = 'approved';
    case Disputed = 'disputed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Received => 'Reçue',
            self::UnderReview => 'En revue',
            self::PartiallyApproved => 'Partiellement approuvée',
            self::Approved => 'Approuvée',
            self::Disputed => 'Litigieuse',
            self::Cancelled => 'Annulée',
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
