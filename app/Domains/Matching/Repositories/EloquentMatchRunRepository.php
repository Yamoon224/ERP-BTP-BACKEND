<?php

namespace App\Domains\Matching\Repositories;

use App\Domains\Matching\Contracts\MatchRunRepositoryContract;
use App\Domains\Matching\DTOs\Discrepancy;
use App\Domains\Matching\DTOs\LineOutcome;
use App\Domains\Matching\DTOs\MatchOutcome;
use App\Domains\Matching\Enums\ActorType;
use App\Domains\Matching\Enums\ReviewStatus;
use App\Models\Invoice;
use App\Models\MatchLineResult;
use App\Models\MatchRun;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Écriture de la piste d'audit du rapprochement.
 *
 * Append-only par conception : `record()` ajoute toujours une exécution, ne
 * met jamais à jour la précédente. C'est ce qui permet de répondre plus tard à
 * « sur quelles données cette facture a-t-elle été validée ? » même après que
 * le PO, les livraisons ou les tolérances ont changé.
 */
final class EloquentMatchRunRepository implements MatchRunRepositoryContract
{
    public function record(Invoice $invoice, MatchOutcome $outcome, ?User $actor, string $trigger): MatchRun
    {
        $matchRun = MatchRun::create([
            'invoice_id' => $invoice->id,
            'actor_type' => $actor === null ? ActorType::System : ActorType::User,
            'actor_id' => $actor?->id,
            'trigger' => $trigger,
            'engine_version' => $outcome->engineVersion,
            'tolerance_snapshot' => $outcome->tolerance->toArray(),
            'status' => $outcome->status,
            'currency' => $outcome->currency,
            'invoiced_amount' => $outcome->invoicedAmount,
            'matched_amount' => $outcome->matchedAmount,
            'unmatched_amount' => $outcome->unmatchedAmount,
            'base_currency' => $outcome->baseCurrency,
            'base_matched_amount' => $outcome->baseMatchedAmount,
            'base_unmatched_amount' => $outcome->baseUnmatchedAmount,
            // Taux appliques figes avec la decision, au meme titre que les
            // tolerances : sans eux, un montant converti serait invérifiable.
            'exchange_rate_snapshot' => $outcome->exchangeRateSnapshot,
            'exception_count' => $outcome->exceptionCount(),
            'evaluated_at' => now(),
        ]);

        $lineResultsByInvoiceLine = [];

        foreach ($outcome->lineOutcomes as $lineOutcome) {
            $lineResultsByInvoiceLine[$lineOutcome->invoiceLineId] = $this->recordLineResult($matchRun, $lineOutcome);
        }

        foreach ($outcome->allDiscrepancies() as $discrepancy) {
            $this->recordException($matchRun, $invoice, $discrepancy, $lineResultsByInvoiceLine);
        }

        return $matchRun;
    }

    public function findOrFail(int $id): MatchRun
    {
        return MatchRun::with([
            'actor',
            'lineResults.invoiceLine',
            'lineResults.purchaseOrderLine',
            'exceptions.reviewer',
            'paymentAuthorization',
        ])->findOrFail($id);
    }

    public function latestForInvoice(int $invoiceId): ?MatchRun
    {
        return MatchRun::with(['actor', 'lineResults', 'exceptions'])
            ->where('invoice_id', $invoiceId)
            ->latest('id')
            ->first();
    }

    /** @return LengthAwarePaginator<int, MatchRun> */
    public function paginateForInvoice(int $invoiceId, array $filters = [], int $perPage = 10): LengthAwarePaginator
    {
        return MatchRun::query()
            ->with(['actor', 'paymentAuthorization'])
            ->withCount(['lineResults', 'exceptions'])
            ->where('invoice_id', $invoiceId)
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->latest('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    private function recordLineResult(MatchRun $matchRun, LineOutcome $lineOutcome): MatchLineResult
    {
        return $matchRun->lineResults()->create([
            'invoice_line_id' => $lineOutcome->invoiceLineId,
            'purchase_order_line_id' => $lineOutcome->purchaseOrderLineId,
            'status' => $lineOutcome->status,
            'quantity_invoiced' => $lineOutcome->quantityInvoiced,
            'quantity_matched' => $lineOutcome->quantityMatched,
            'quantity_unmatched' => $lineOutcome->quantityUnmatched,
            'unit_price_invoiced' => $lineOutcome->unitPriceInvoiced,
            'unit_price_ordered' => $lineOutcome->unitPriceOrdered,
            'price_variance_ratio' => $lineOutcome->priceVarianceRatio,
            'matched_amount' => $lineOutcome->matchedAmount,
            'evidence' => $lineOutcome->evidence,
        ]);
    }

    /** @param  array<int, MatchLineResult>  $lineResultsByInvoiceLine */
    private function recordException(
        MatchRun $matchRun,
        Invoice $invoice,
        Discrepancy $discrepancy,
        array $lineResultsByInvoiceLine,
    ): void {
        $lineResult = $discrepancy->invoiceLineId !== null
            ? ($lineResultsByInvoiceLine[$discrepancy->invoiceLineId] ?? null)
            : null;

        $matchRun->exceptions()->create([
            'invoice_id' => $invoice->id,
            'invoice_line_id' => $discrepancy->invoiceLineId,
            'match_line_result_id' => $lineResult?->id,
            'type' => $discrepancy->type,
            'severity' => $discrepancy->severity(),
            'message' => $discrepancy->message,
            'context' => $discrepancy->context,
            'review_status' => ReviewStatus::Open,
        ]);
    }
}
