<?php

namespace App\Domains\Payments\Contracts;

use App\Models\Invoice;
use App\Models\MatchRun;
use App\Models\PaymentAuthorization;

/**
 * Point d'entree unique par lequel un paiement devient autorisable.
 *
 * Le domaine Matching en depend sans connaitre l'implementation : c'est ici
 * qu'on brancherait demain un circuit de validation hierarchique (double
 * signature au-dela d'un seuil) sans toucher au moteur.
 */
interface PaymentAuthorizerContract
{
    /**
     * Traduit un rapprochement en droit a payer. Renvoie null si le montant
     * rapproche est nul : aucune autorisation n'est creee pour zero euro.
     */
    public function authorizeFromMatchRun(Invoice $invoice, MatchRun $matchRun): ?PaymentAuthorization;
}
