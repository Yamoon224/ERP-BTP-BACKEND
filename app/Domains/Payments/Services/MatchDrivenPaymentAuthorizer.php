<?php

namespace App\Domains\Payments\Services;

use App\Domains\Payments\Contracts\PaymentAuthorizationRepositoryContract;
use App\Domains\Payments\Contracts\PaymentAuthorizerContract;
use App\Domains\Shared\Support\Decimal;
use App\Models\Invoice;
use App\Models\MatchRun;
use App\Models\PaymentAuthorization;

/**
 * Seule voie par laquelle un paiement devient autorisable dans le système.
 *
 * Le montant est repris tel quel du rapprochement : il n'est jamais recalculé
 * ici, et aucun chemin ne permet d'autoriser un montant qui ne provienne pas
 * d'une exécution du moteur. C'est ce qui rend la règle « pas de paiement pour
 * la portion non rapprochée » structurelle plutôt que déclarative.
 */
final class MatchDrivenPaymentAuthorizer implements PaymentAuthorizerContract
{
    public function __construct(
        private readonly PaymentAuthorizationRepositoryContract $authorizations,
    ) {}

    public function authorizeFromMatchRun(Invoice $invoice, MatchRun $matchRun): ?PaymentAuthorization
    {
        $amount = (float) $matchRun->matched_amount;

        if (! Decimal::isPositive($amount)) {
            // Rien de rapproché : on révoque l'autorisation précédente au lieu
            // de la laisser survivre à un recalcul devenu défavorable.
            $this->authorizations->revokeActiveForInvoice($invoice->id);

            return null;
        }

        return $this->authorizations->issue($invoice, $matchRun, $amount);
    }
}
