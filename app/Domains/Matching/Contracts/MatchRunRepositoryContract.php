<?php

namespace App\Domains\Matching\Contracts;

use App\Domains\Matching\DTOs\MatchOutcome;
use App\Models\Invoice;
use App\Models\MatchRun;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface MatchRunRepositoryContract
{
    /**
     * Persiste une execution du moteur et son detail ligne a ligne.
     *
     * @param  User|null  $actor  null = decision du moteur (ActorType::System)
     */
    public function record(Invoice $invoice, MatchOutcome $outcome, ?User $actor, string $trigger): MatchRun;

    public function findOrFail(int $id): MatchRun;

    public function latestForInvoice(int $invoiceId): ?MatchRun;

    /** @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, MatchRun>
     */
    public function paginateForInvoice(int $invoiceId, array $filters = [], int $perPage = 10): LengthAwarePaginator;

    /**
     * Toutes les executions, toutes factures confondues.
     *
     * C'est la vue « registre » : elle repond a « qu'a decide le moteur cette
     * semaine, et qui a rejoue quoi », question qu'on ne peut pas poser facture
     * par facture.
     *
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, MatchRun>
     */
    public function paginate(array $filters = [], int $perPage = 10): LengthAwarePaginator;
}
