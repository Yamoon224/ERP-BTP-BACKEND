<?php

namespace App\Domains\Invoicing\Exceptions;

use App\Domains\Shared\Exceptions\DomainException;

/**
 * Refus de changer la devise de reglement d'une facture.
 *
 * Changer la devise n'est pas un detail d'affichage : c'est l'unite dans
 * laquelle le prix facture sera confronte au prix commande, et donc l'unite du
 * montant autorise au paiement. Le geste est donc reserve aux factures dont
 * rien n'est encore parti — une fois le virement constate, corriger la devise
 * reecrirait a posteriori le sens d'un paiement deja execute.
 */
final class InvoiceCurrencyNotChangeableException extends DomainException
{
    public static function becauseCancelled(string $reference): self
    {
        return new self(
            message: "La facture {$reference} est annulee : sa devise n'a plus d'effet.",
            errorCode: 'invoice_currency_not_changeable',
            statusCode: 409,
            context: ['reference' => $reference, 'reason' => 'cancelled'],
        );
    }

    public static function becauseSettled(string $reference): self
    {
        return new self(
            message: "La facture {$reference} a deja fait l'objet d'un reglement : sa devise ne peut plus etre modifiee.",
            errorCode: 'invoice_currency_not_changeable',
            statusCode: 409,
            context: ['reference' => $reference, 'reason' => 'settled'],
        );
    }
}
