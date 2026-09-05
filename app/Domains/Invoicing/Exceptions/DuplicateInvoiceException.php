<?php

namespace App\Domains\Invoicing\Exceptions;

use App\Domains\Shared\Exceptions\DomainException;

/**
 * Barriere anti-double paiement la plus en amont : une meme reference de
 * facture, chez un meme fournisseur, ne peut pas entrer deux fois dans le
 * systeme.
 */
final class DuplicateInvoiceException extends DomainException
{
    public static function make(string $reference, string $supplierId): self
    {
        return new self(
            message: "Une facture portant la reference {$reference} existe deja pour ce fournisseur.",
            errorCode: 'duplicate_invoice',
            statusCode: 409,
            context: ['reference' => $reference, 'supplier_id' => $supplierId],
        );
    }
}
