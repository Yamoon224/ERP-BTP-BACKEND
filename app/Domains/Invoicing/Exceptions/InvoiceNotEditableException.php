<?php

namespace App\Domains\Invoicing\Exceptions;

use App\Domains\Shared\Exceptions\DomainException;

final class InvoiceNotEditableException extends DomainException
{
    public static function alreadyCancelled(string $reference): self
    {
        return new self(
            message: "La facture {$reference} est deja annulee.",
            errorCode: 'invoice_already_cancelled',
            statusCode: 409,
            context: ['reference' => $reference],
        );
    }
}
