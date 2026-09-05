<?php

namespace App\Domains\Matching\Exceptions;

use App\Domains\Shared\Exceptions\DomainException;

final class InvoiceNotMatchableException extends DomainException
{
    public static function becauseCancelled(string $reference): self
    {
        return new self(
            message: "La facture {$reference} est annulee : elle ne peut plus etre rapprochee.",
            errorCode: 'invoice_cancelled',
            statusCode: 409,
            context: ['reference' => $reference],
        );
    }

    public static function becauseDisputed(string $reference): self
    {
        return new self(
            message: "La facture {$reference} est en litige apres un arbitrage defavorable : elle doit etre corrigee a la source (avoir ou facture rectificative) avant tout nouveau rapprochement.",
            errorCode: 'invoice_disputed',
            statusCode: 409,
            context: ['reference' => $reference],
        );
    }
}
