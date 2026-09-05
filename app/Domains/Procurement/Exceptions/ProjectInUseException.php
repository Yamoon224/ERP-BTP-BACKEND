<?php

namespace App\Domains\Procurement\Exceptions;

use App\Domains\Shared\Exceptions\DomainException;

/**
 * Un chantier deja engage par un bon de commande ne se supprime pas : les
 * montants agreges par chantier dans le pilotage perdraient leur referent.
 * La desactivation joue le meme role sans casser l'historique.
 */
final class ProjectInUseException extends DomainException
{
    public static function make(string $name, int $purchaseOrders): self
    {
        return new self(
            message: "Le chantier {$name} porte {$purchaseOrders} bon(s) de commande : il ne peut pas etre supprime. Desactivez-le pour le retirer des listes de saisie.",
            errorCode: 'project_in_use',
            statusCode: 409,
            context: ['name' => $name, 'purchase_orders' => $purchaseOrders],
        );
    }
}
