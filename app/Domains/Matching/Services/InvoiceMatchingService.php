<?php

namespace App\Domains\Matching\Services;

use App\Domains\Invoicing\Contracts\InvoiceRepositoryContract;
use App\Domains\Invoicing\Enums\InvoiceStatus;
use App\Domains\Matching\Contracts\MatchingEngineContract;
use App\Domains\Matching\Contracts\MatchRunRepositoryContract;
use App\Domains\Matching\Enums\MatchStatus;
use App\Domains\Matching\Exceptions\InvoiceNotMatchableException;
use App\Domains\Payments\Contracts\PaymentAuthorizerContract;
use App\Models\Invoice;
use App\Models\MatchRun;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Orchestration d'un rapprochement complet : assembler les trois voies,
 * appliquer le moteur, archiver la décision, en déduire le droit à paiement et
 * l'état de la facture.
 *
 * Ce service ne contient aucune règle de rapprochement — elles vivent toutes
 * dans le moteur. Il ne fait que coordonner des collaborateurs injectés, ce qui
 * permet de tester la coordination et les règles séparément.
 */
final class InvoiceMatchingService
{
    /** Origine d'un rapprochement, tracée dans `match_runs.trigger`. */
    public const TRIGGER_INVOICE_SUBMITTED = 'invoice_submitted';

    public const TRIGGER_MANUAL = 'manual';

    public const TRIGGER_DELIVERY_ACCEPTED = 'delivery_accepted';

    public const TRIGGER_EXCEPTION_REVIEWED = 'exception_reviewed';

    /**
     * Changement de devise de reglement. Rejouer est obligatoire : les prix ne
     * se comparent plus dans la meme unite, donc le montant autorise change.
     */
    public const TRIGGER_CURRENCY_CHANGED = 'currency_changed';

    public function __construct(
        private readonly MatchInputAssembler $assembler,
        private readonly MatchingEngineContract $engine,
        private readonly MatchRunRepositoryContract $matchRuns,
        private readonly PaymentAuthorizerContract $paymentAuthorizer,
        private readonly InvoiceRepositoryContract $invoices,
    ) {}

    /**
     * Rejoue le rapprochement d'une facture et renvoie l'exécution archivée.
     *
     * @param  User|null  $actor  null lorsque la décision est celle du moteur, sans intervention humaine
     *
     * @throws InvoiceNotMatchableException
     */
    public function match(Invoice $invoice, ?User $actor = null, string $trigger = self::TRIGGER_MANUAL): MatchRun
    {
        if ($invoice->status === InvoiceStatus::Cancelled) {
            throw InvoiceNotMatchableException::becauseCancelled($invoice->reference);
        }

        // Une facture mise en litige par un arbitrage defavorable ne se
        // re-rapproche pas toute seule : la ré-autoriser automatiquement
        // annulerait la décision humaine qui vient d'être prise.
        if ($invoice->status === InvoiceStatus::Disputed) {
            throw InvoiceNotMatchableException::becauseDisputed($invoice->reference);
        }

        return DB::transaction(function () use ($invoice, $actor, $trigger): MatchRun {
            $input = $this->assembler->assemble($invoice);
            $outcome = $this->engine->evaluate($input);

            $matchRun = $this->matchRuns->record($invoice, $outcome, $actor, $trigger);
            $this->paymentAuthorizer->authorizeFromMatchRun($invoice, $matchRun);
            $this->invoices->updateStatus($invoice, $this->deriveInvoiceStatus($outcome->status));

            return $matchRun->load(['actor', 'lineResults', 'exceptions', 'paymentAuthorization']);
        });
    }

    /**
     * Rapproche toutes les factures d'un bon de commande. Utilisé quand une
     * livraison est acceptée : les factures déjà reçues mais bloquées faute de
     * marchandise deviennent alors payables, sans intervention humaine.
     *
     * @return list<MatchRun>
     */
    public function matchAllForPurchaseOrder(int $purchaseOrderId, ?User $actor, string $trigger): array
    {
        $invoices = Invoice::query()
            ->where('purchase_order_id', $purchaseOrderId)
            ->whereNotIn('status', [InvoiceStatus::Cancelled, InvoiceStatus::Disputed])
            // Ordre de soumission : la première facture arrivée est servie en
            // premier sur les quantités reçues disponibles.
            ->orderBy('id')
            ->get();

        return $invoices
            ->map(fn (Invoice $invoice): MatchRun => $this->match($invoice, $actor, $trigger))
            ->all();
    }

    /**
     * Traduction du verdict de rapprochement en état de facture. Volontairement
     * total (toutes les branches couvertes) : un état de facture non déterminé
     * laisserait une créance dans un flou impossible à auditer.
     */
    private function deriveInvoiceStatus(MatchStatus $matchStatus): InvoiceStatus
    {
        return match ($matchStatus) {
            MatchStatus::Matched => InvoiceStatus::Approved,
            MatchStatus::PartiallyMatched => InvoiceStatus::PartiallyApproved,
            MatchStatus::Exception => InvoiceStatus::UnderReview,
            MatchStatus::Unmatched => InvoiceStatus::Received,
        };
    }
}
