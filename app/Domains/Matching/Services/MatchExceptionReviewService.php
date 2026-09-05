<?php

namespace App\Domains\Matching\Services;

use App\Domains\Invoicing\Contracts\InvoiceRepositoryContract;
use App\Domains\Invoicing\Enums\InvoiceStatus;
use App\Domains\Matching\Contracts\MatchExceptionRepositoryContract;
use App\Domains\Matching\Enums\ReviewStatus;
use App\Domains\Matching\Exceptions\ExceptionAlreadyReviewedException;
use App\Domains\Payments\Contracts\PaymentAuthorizationRepositoryContract;
use App\Models\MatchException;
use App\Models\MatchRun;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Arbitrage humain des écarts (règle fonctionnelle n°6).
 *
 * L'arbitrage est nominatif, horodaté et motivé — mais il n'écrit jamais
 * directement un montant payable :
 *
 *  - ACCEPTÉ : l'écart est mémorisé comme dérogation, puis le rapprochement est
 *    REJOUÉ. C'est le moteur, et lui seul, qui recalcule ce qui devient payable ;
 *    la nouvelle exécution porte le nom du relecteur.
 *  - REFUSÉ : la facture est contestée. Elle passe en litige et l'autorisation
 *    de paiement en cours est révoquée. Un refus est terminal : la facture doit
 *    être corrigée à la source (avoir, facture rectificative), le système ne la
 *    ré-autorisera pas tout seul.
 *
 * Ce choix garantit aussi qu'un arbitrage converge : un refus ne relance pas le
 * moteur, qui re-détecterait le même écart et rouvrirait une exception à
 * l'infini.
 */
final class MatchExceptionReviewService
{
    public function __construct(
        private readonly MatchExceptionRepositoryContract $exceptions,
        private readonly InvoiceMatchingService $matchingService,
        private readonly InvoiceRepositoryContract $invoices,
        private readonly PaymentAuthorizationRepositoryContract $authorizations,
    ) {}

    /**
     * @return array{exception: MatchException, match_run: MatchRun|null}
     *
     * @throws ExceptionAlreadyReviewedException
     */
    public function review(
        MatchException $exception,
        ReviewStatus $decision,
        User $reviewer,
        ?string $note = null,
    ): array {
        if ($exception->review_status->isResolved()) {
            throw ExceptionAlreadyReviewedException::make($exception->id, $exception->review_status);
        }

        return DB::transaction(function () use ($exception, $decision, $reviewer, $note): array {
            $reviewed = $this->exceptions->markReviewed($exception, $decision, $reviewer, $note);

            $matchRun = null;

            if ($decision === ReviewStatus::Approved) {
                $matchRun = $this->rematchAfterApproval($reviewed, $reviewer);
            } else {
                $this->disputeAfterRejection($reviewed);
            }

            return ['exception' => $reviewed, 'match_run' => $matchRun];
        });
    }

    private function rematchAfterApproval(MatchException $exception, User $reviewer): ?MatchRun
    {
        // Une facture deja mise en litige par un autre arbitrage ne repasse pas
        // par le moteur : la derogation est enregistree, mais c'est la
        // correction de la facture qui debloquera le circuit, pas ce clic.
        if ($exception->invoice->status === InvoiceStatus::Disputed) {
            return null;
        }

        return $this->matchingService->match(
            $exception->invoice,
            $reviewer,
            InvoiceMatchingService::TRIGGER_EXCEPTION_REVIEWED,
        );
    }

    private function disputeAfterRejection(MatchException $exception): void
    {
        $invoice = $exception->invoice;

        $this->authorizations->revokeActiveForInvoice($invoice->id);
        $this->invoices->updateStatus($invoice, InvoiceStatus::Disputed);
    }
}
