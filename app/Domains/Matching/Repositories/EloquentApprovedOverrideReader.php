<?php

namespace App\Domains\Matching\Repositories;

use App\Domains\Matching\Contracts\ApprovedOverrideReaderContract;
use App\Domains\Matching\Enums\ReviewStatus;
use App\Models\MatchException;

/**
 * Écarts qu'un relecteur a explicitement acceptés, à re-appliquer aux
 * rapprochements suivants de la même facture.
 *
 * Sans cette mémoire, arbitrer un écart ne servirait à rien : le rapprochement
 * relancé juste après le rebloquerait à l'identique.
 */
final class EloquentApprovedOverrideReader implements ApprovedOverrideReaderContract
{
    public function approvedOverridesForInvoice(string $invoiceId): array
    {
        $overrides = [];

        $exceptions = MatchException::query()
            ->where('invoice_id', $invoiceId)
            ->where('review_status', ReviewStatus::Approved)
            ->whereNotNull('invoice_line_id')
            ->get(['invoice_line_id', 'type']);

        foreach ($exceptions as $exception) {
            // Un arbitrage favorable sur un écart non dérogeable (fournisseur
            // erroné, par exemple) ne débloque rien : la facture doit être
            // corrigée à la source.
            if (! $exception->type->isOverridable()) {
                continue;
            }

            $lineId = $exception->invoice_line_id;
            $overrides[$lineId] ??= [];

            if (! in_array($exception->type, $overrides[$lineId], true)) {
                $overrides[$lineId][] = $exception->type;
            }
        }

        return $overrides;
    }
}
