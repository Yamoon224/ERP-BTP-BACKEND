<?php

namespace App\Domains\Matching\Enums;

enum ReviewStatus: string
{
    case Open = 'open';
    case Approved = 'approved';
    case Rejected = 'rejected';

    public function isResolved(): bool
    {
        return $this !== self::Open;
    }

    public function label(): string
    {
        return match ($this) {
            self::Open => 'À arbitrer',
            self::Approved => 'Accepté',
            self::Rejected => 'Refusé',
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
