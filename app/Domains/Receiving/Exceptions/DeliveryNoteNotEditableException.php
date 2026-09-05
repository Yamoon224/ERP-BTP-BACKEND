<?php

namespace App\Domains\Receiving\Exceptions;

use App\Domains\Receiving\Enums\DeliveryNoteStatus;
use App\Domains\Shared\Exceptions\DomainException;

/**
 * Un bon de livraison deja arbitre (accepte ou refuse) ne se reouvre pas :
 * modifier retroactivement une quantite recue reviendrait a deplacer le
 * plafond de paiement d une facture deja rapprochee.
 */
final class DeliveryNoteNotEditableException extends DomainException
{
    public static function alreadyReviewed(string $reference, DeliveryNoteStatus $status): self
    {
        return new self(
            message: "Le bon de livraison {$reference} est deja {$status->label()} : son statut ne peut plus changer.",
            errorCode: 'delivery_note_already_reviewed',
            statusCode: 409,
            context: ['reference' => $reference, 'status' => $status->value],
        );
    }
}
