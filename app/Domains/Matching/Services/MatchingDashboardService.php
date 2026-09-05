<?php

namespace App\Domains\Matching\Services;

use App\Domains\Invoicing\Enums\InvoiceStatus;
use App\Domains\Matching\Enums\ReviewStatus;
use App\Domains\Payments\Enums\PaymentAuthorizationStatus;
use App\Models\Invoice;
use App\Models\MatchException;
use App\Models\PaymentAuthorization;
use Illuminate\Support\Facades\DB;

/**
 * Indicateurs de pilotage du contrôle : combien d'argent est aujourd'hui
 * autorisé, combien reste bloqué, et quelle est la charge de revue en attente.
 *
 * Tous les cumuls portent sur les colonnes en **devise de référence**.
 * Additionner directement `amount` mélangerait des euros, des dollars et des
 * francs CFA — un total de « 18 968 » sans devise serait au mieux inutile, au
 * pire trompeur sur un tableau de bord financier. La ventilation par devise
 * d'origine reste disponible à côté, pour ne pas perdre l'information.
 *
 * Ces chiffres sont recalculés à la demande plutôt que stockés : un compteur
 * dénormalisé qui dérive silencieusement serait pire que pas de compteur du
 * tout sur un tableau de bord de contrôle financier.
 */
final class MatchingDashboardService
{
    /** @return array<string, mixed> */
    public function summary(): array
    {
        return [
            'invoices' => $this->invoiceCountsByStatus(),
            'exceptions' => [
                'open' => MatchException::query()->open()->count(),
                'by_type' => $this->openExceptionsByType(),
                'by_severity' => $this->openExceptionsBySeverity(),
            ],
            'amounts' => $this->amounts(),
        ];
    }

    /** @return array<string, int> */
    private function invoiceCountsByStatus(): array
    {
        $counts = Invoice::query()
            ->groupBy('status')
            ->selectRaw('status, COUNT(*) as total')
            ->pluck('total', 'status')
            ->all();

        // Tous les statuts sont présents, même à zéro : un tableau de bord qui
        // omet les colonnes vides oblige le lecteur à deviner.
        $summary = [];
        foreach (InvoiceStatus::cases() as $status) {
            $summary[$status->value] = (int) ($counts[$status->value] ?? 0);
        }

        return $summary;
    }

    /** @return array<string, int> */
    private function openExceptionsByType(): array
    {
        return MatchException::query()
            ->open()
            ->groupBy('type')
            ->selectRaw('type, COUNT(*) as total')
            ->pluck('total', 'type')
            ->map(fn ($total): int => (int) $total)
            ->all();
    }

    /** @return array<string, int> */
    private function openExceptionsBySeverity(): array
    {
        return MatchException::query()
            ->open()
            ->groupBy('severity')
            ->selectRaw('severity, COUNT(*) as total')
            ->pluck('total', 'severity')
            ->map(fn ($total): int => (int) $total)
            ->all();
    }

    /** @return array<string, mixed> */
    private function amounts(): array
    {
        $baseCurrency = (string) config('matching.base_currency');

        $authorized = (float) PaymentAuthorization::query()
            ->where('status', PaymentAuthorizationStatus::Active->value)
            ->sum('base_amount');

        // Le « bloqué » se lit sur le dernier rapprochement de chaque facture
        // non annulée : c'est la part facturée qu'aucun BL ou aucun prix
        // conforme ne couvre aujourd'hui.
        $latestRuns = DB::table('match_runs')
            ->selectRaw('MAX(id) as id')
            ->whereIn('invoice_id', DB::table('invoices')->select('id')->where('status', '!=', InvoiceStatus::Cancelled->value))
            ->groupBy('invoice_id');

        $blocked = (float) DB::table('match_runs')
            ->joinSub($latestRuns, 'latest', 'latest.id', '=', 'match_runs.id')
            ->sum('match_runs.base_unmatched_amount');

        return [
            'authorized_for_payment' => round($authorized, 2),
            'blocked' => round($blocked, 2),
            'currency' => $baseCurrency,
            // Ventilation par devise réellement facturée : c'est dans celle-ci
            // que les virements seront émis.
            'by_currency' => $this->authorizedByCurrency(),
        ];
    }

    /**
     * Montants autorisés par devise de règlement, avec leur contre-valeur.
     *
     * @return list<array<string, mixed>>
     */
    private function authorizedByCurrency(): array
    {
        // Requete brute plutot qu'Eloquent : le resultat est un agregat, pas
        // une collection de modeles, et le presenter comme tel serait trompeur.
        return DB::table('payment_authorizations')
            ->where('status', PaymentAuthorizationStatus::Active->value)
            ->groupBy('currency')
            ->selectRaw('currency, SUM(amount) as total, SUM(base_amount) as base_total')
            ->orderByDesc('base_total')
            ->get()
            ->map(fn ($row): array => [
                'currency' => (string) $row->currency,
                'amount' => round((float) $row->total, 2),
                'base_amount' => round((float) $row->base_total, 2),
            ])
            ->all();
    }

    /** Nombre d'écarts encore ouverts, pour un badge de navigation. */
    public function openExceptionCount(): int
    {
        return MatchException::query()->where('review_status', ReviewStatus::Open)->count();
    }
}
