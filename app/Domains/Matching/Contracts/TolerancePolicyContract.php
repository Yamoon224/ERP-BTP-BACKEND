<?php

namespace App\Domains\Matching\Contracts;

use App\Domains\Matching\DTOs\InvoiceLineInput;
use App\Domains\Matching\DTOs\InvoiceMatchInput;
use App\Domains\Matching\DTOs\Tolerance;

/**
 * Fournit les seuils applicables. Extrait du moteur pour respecter
 * l'Open/Closed : une politique par fournisseur, par famille d'article ou par
 * chantier s'ajoute en implementant ce contrat, sans modifier le moteur.
 */
interface TolerancePolicyContract
{
    public function forLine(InvoiceMatchInput $input, InvoiceLineInput $line): Tolerance;

    /**
     * Seuils de reference a archiver dans l'execution. Une politique variable
     * y expose ses parametres, pas seulement ses valeurs par defaut.
     */
    public function snapshot(InvoiceMatchInput $input): Tolerance;
}
