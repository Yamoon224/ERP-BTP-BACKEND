<?php

namespace App\Domains\Payments\Contracts;

use App\Models\Invoice;
use App\Models\MatchRun;
use App\Models\PaymentAuthorization;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface PaymentAuthorizationRepositoryContract
{
    /** @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, PaymentAuthorization>
     */
    public function paginate(array $filters = [], int $perPage = 10): LengthAwarePaginator;

    public function activeForInvoice(int $invoiceId): ?PaymentAuthorization;

    /**
     * Cree l'autorisation issue d'un rapprochement et remplace la precedente.
     * L'ancienne n'est jamais supprimee : elle passe en `superseded`.
     */
    public function issue(Invoice $invoice, MatchRun $matchRun, float $amount): PaymentAuthorization;

    /** Revoque l'autorisation active d'une facture (annulation, litige). */
    public function revokeActiveForInvoice(int $invoiceId): void;

    /**
     * Enregistre le reglement effectif d'une autorisation. Le montant n'est
     * pas un parametre : c'est celui qu'a calcule le moteur.
     *
     * @param  array{payment_reference: string, payment_method?: string|null, settled_at?: string|null}  $payment
     */
    public function settle(PaymentAuthorization $authorization, array $payment, User $settler): PaymentAuthorization;
}
