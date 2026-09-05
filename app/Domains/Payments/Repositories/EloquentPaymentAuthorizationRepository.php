<?php

namespace App\Domains\Payments\Repositories;

use App\Domains\Payments\Contracts\PaymentAuthorizationRepositoryContract;
use App\Domains\Payments\Enums\PaymentAuthorizationStatus;
use App\Domains\Payments\Exceptions\PaymentNotSettleableException;
use App\Domains\Shared\Support\Sort;
use App\Models\Invoice;
use App\Models\MatchRun;
use App\Models\PaymentAuthorization;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

final class EloquentPaymentAuthorizationRepository implements PaymentAuthorizationRepositoryContract
{
    /**
     * Colonnes offertes au tri. L'allowlist n'est pas une precaution de style :
     * un `orderBy` aliment directement par la requete serait une injection SQL.
     *
     * @var array<string, string>
     */
    private const SORTABLE = [
        'id' => 'id',
        'amount' => 'amount',
        'currency' => 'currency',
        'status' => 'status',
        'authorized_at' => 'authorized_at',
        'settled_at' => 'settled_at',
        'match_run_id' => 'match_run_id',
    ];

    /** @return LengthAwarePaginator<int, PaymentAuthorization> */
    public function paginate(array $filters = [], int $perPage = 10): LengthAwarePaginator
    {
        return PaymentAuthorization::query()
            ->with(['invoice.supplier', 'matchRun', 'settler'])
            ->when($filters['invoice_id'] ?? null, fn ($query, $id) => $query->where('invoice_id', $id))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['currency'] ?? null, fn ($query, $currency) => $query->where('currency', $currency))
            ->when($filters['supplier_id'] ?? null, fn ($query, $id) => $query->whereHas(
                'invoice',
                fn ($invoiceQuery) => $invoiceQuery->where('supplier_id', $id),
            ))
            // `settled` est un tri-etat : absent = tout, vrai = regle, faux =
            // en attente de reglement.
            ->when(
                ! in_array($filters['settled'] ?? null, [null, ''], true),
                fn ($query) => filter_var($filters['settled'], FILTER_VALIDATE_BOOLEAN)
                    ? $query->whereNotNull('settled_at')
                    : $query->whereNull('settled_at'),
            )
            ->when($filters['search'] ?? null, fn ($query, $search) => $query->where(
                fn ($subQuery) => $subQuery
                    ->where('payment_reference', 'like', "%{$search}%")
                    ->orWhereHas('invoice', fn ($invoiceQuery) => $invoiceQuery
                        ->where('reference', 'like', "%{$search}%")
                        ->orWhereHas('supplier', fn ($supplierQuery) => $supplierQuery->where('name', 'like', "%{$search}%"))),
            ))
            ->tap(fn ($query) => Sort::apply($query, $filters, self::SORTABLE, 'id', 'desc'))
            ->paginate($perPage)
            ->withQueryString();
    }

    public function activeForInvoice(int $invoiceId): ?PaymentAuthorization
    {
        return PaymentAuthorization::query()
            ->active()
            ->where('invoice_id', $invoiceId)
            ->latest('id')
            ->first();
    }

    /**
     * Le montant autorisé est exprimé dans la devise de la facture — c'est
     * dans celle-ci que le fournisseur sera réglé. La contre-valeur en devise
     * de référence est reprise du rapprochement (et non recalculée ici) pour
     * qu'autorisation et décision citent rigoureusement le même taux.
     */
    public function issue(Invoice $invoice, MatchRun $matchRun, float $amount): PaymentAuthorization
    {
        return DB::transaction(function () use ($invoice, $matchRun, $amount): PaymentAuthorization {
            // L'ancienne autorisation est déclassée, jamais supprimée : la
            // trace de ce qui a été autorisé à un instant T doit survivre au
            // recalcul.
            $this->supersedeActiveForInvoice($invoice->id);

            return PaymentAuthorization::create([
                'invoice_id' => $invoice->id,
                'match_run_id' => $matchRun->id,
                'currency' => $matchRun->currency,
                'amount' => $amount,
                'base_currency' => $matchRun->base_currency,
                'base_amount' => $matchRun->base_matched_amount,
                'exchange_rate' => $this->appliedBaseRate($matchRun),
                'status' => PaymentAuthorizationStatus::Active,
                'authorized_at' => now(),
            ]);
        });
    }

    public function revokeActiveForInvoice(int $invoiceId): void
    {
        PaymentAuthorization::query()
            ->active()
            ->unsettled()
            ->where('invoice_id', $invoiceId)
            ->update([
                'status' => PaymentAuthorizationStatus::Revoked,
                'updated_at' => now(),
            ]);
    }

    /**
     * Enregistre le reglement. Le montant n'est pas saisi : il est celui de
     * l'autorisation, donc celui qu'a calcule le moteur. Laisser l'operateur
     * taper une somme ici viderait de son sens tout le controle a 3 voies.
     *
     * @param  array{payment_reference: string, payment_method?: string|null, settled_at?: string|null}  $payment
     *
     * @throws PaymentNotSettleableException
     */
    public function settle(PaymentAuthorization $authorization, array $payment, User $settler): PaymentAuthorization
    {
        if ($authorization->isSettled()) {
            throw PaymentNotSettleableException::alreadySettled($authorization);
        }

        if ($authorization->status !== PaymentAuthorizationStatus::Active) {
            throw PaymentNotSettleableException::notActive($authorization);
        }

        $authorization->update([
            'settled_at' => $payment['settled_at'] ?? now(),
            'payment_reference' => $payment['payment_reference'],
            'payment_method' => $payment['payment_method'] ?? null,
            'settled_by' => $settler->id,
        ]);

        return $authorization->refresh()->load(['invoice.supplier', 'settler']);
    }

    /**
     * Une autorisation deja reglee n'est jamais declassee : le virement est
     * parti, la trace de ce qui l'a justifie doit rester intacte.
     */
    private function supersedeActiveForInvoice(int $invoiceId): void
    {
        PaymentAuthorization::query()
            ->active()
            ->unsettled()
            ->where('invoice_id', $invoiceId)
            ->update([
                'status' => PaymentAuthorizationStatus::Superseded,
                'updated_at' => now(),
            ]);
    }

    /** Taux facture → devise de référence retenu par le rapprochement. */
    private function appliedBaseRate(MatchRun $matchRun): float
    {
        $snapshot = $matchRun->exchange_rate_snapshot ?? [];

        return (float) ($snapshot['invoice_to_base']['rate'] ?? 1.0);
    }
}
