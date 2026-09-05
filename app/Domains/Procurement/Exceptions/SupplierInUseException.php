<?php

namespace App\Domains\Procurement\Exceptions;

use App\Domains\Shared\Exceptions\DomainException;

/**
 * Un fournisseur cite par un bon de commande ou une facture ne se supprime pas.
 *
 * Le referentiel est ce qui rend une decision archivee relisible : effacer le
 * fournisseur d'une facture rapprochee il y a deux ans rendrait la piste
 * d'audit incomprehensible. Le geste attendu est la desactivation, qui retire
 * le fournisseur des listes de saisie sans toucher a l'historique.
 */
final class SupplierInUseException extends DomainException
{
    public static function make(string $name, int $documents): self
    {
        return new self(
            message: "Le fournisseur {$name} est rattache a {$documents} document(s) : il ne peut pas etre supprime. Desactivez-le pour le retirer des listes de saisie.",
            errorCode: 'supplier_in_use',
            statusCode: 409,
            context: ['name' => $name, 'documents' => $documents],
        );
    }
}
