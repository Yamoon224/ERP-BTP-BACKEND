<?php

namespace App\Domains\Matching\DTOs;

use App\Domains\Matching\Enums\DiscrepancySeverity;
use App\Domains\Matching\Enums\DiscrepancyType;

/**
 * Un ecart constate par le moteur. Porte son contexte chiffre : le relecteur
 * doit pouvoir arbitrer sans rouvrir les trois documents.
 */
final readonly class Discrepancy
{
    /** @param  array<string, mixed>  $context */
    public function __construct(
        public DiscrepancyType $type,
        public string $message,
        public array $context = [],
        public ?string $invoiceLineId = null,
    ) {}

    public function severity(): DiscrepancySeverity
    {
        return $this->type->severity();
    }
}
