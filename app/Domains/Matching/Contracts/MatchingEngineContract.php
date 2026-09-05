<?php

namespace App\Domains\Matching\Contracts;

use App\Domains\Matching\DTOs\InvoiceMatchInput;
use App\Domains\Matching\DTOs\MatchOutcome;

/**
 * Coeur metier du rapprochement a 3 voies, isole derriere un contrat.
 *
 * Une implementation ne doit ni lire ni ecrire en base : elle recoit une
 * photographie complete (InvoiceMatchInput) et rend un verdict (MatchOutcome).
 * C'est ce qui permet de tester les regles unitairement, et de substituer un
 * moteur alternatif (rapprochement a 2 voies pour les prestations sans BL, par
 * exemple) sans toucher a l'orchestration.
 */
interface MatchingEngineContract
{
    public function evaluate(InvoiceMatchInput $input): MatchOutcome;

    /** Version de la logique appliquee, tracee dans chaque execution. */
    public function version(): string;
}
