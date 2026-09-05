<?php

namespace App\Domains\Invoicing\Services;

use App\Domains\Matching\Enums\MatchStatus;
use App\Models\Invoice;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as PdfDocument;

/**
 * Rendu PDF d'une facture et de son verdict de rapprochement.
 *
 * Le formatage des montants est fait ici, pas dans le gabarit : le nombre de
 * decimales depend de la devise, et le franc CFA n'en a aucune. Un
 * `number_format($x, 2)` code en dur dans une vue Blade inventerait des
 * centimes de franc CFA sur un document que quelqu'un archivera.
 */
final class InvoicePdfGenerator
{
    public function generate(Invoice $invoice, ?User $generatedBy = null): PdfDocument
    {
        $currency = $invoice->currency;
        $decimals = $currency->decimals();
        $symbol = $currency->symbol();

        // Espace insecable etroit entre les groupes et avant le symbole : sur un
        // document imprime, « 3 960 000 F CFA » coupe en fin de ligne devient
        // illisible.
        $formatMoney = static fn (float|string|null $amount): string => $amount === null
            ? '—'
            : number_format((float) $amount, $decimals, ',', "\u{202f}")."\u{202f}".$symbol;

        $formatQuantity = static fn (float|string|null $quantity): string => $quantity === null
            ? '—'
            : rtrim(rtrim(number_format((float) $quantity, 3, ',', "\u{202f}"), '0'), ',');

        $matchRun = $invoice->latestMatchRun;

        return Pdf::loadView('pdf.invoice', [
            'invoice' => $invoice,
            'currency' => $currency,
            'matchRun' => $matchRun,
            'verdictChip' => $this->chipFor($matchRun?->status),
            'formatMoney' => $formatMoney,
            'formatQuantity' => $formatQuantity,
            'generatedAt' => now()->format('d/m/Y \à H:i'),
            'generatedBy' => $generatedBy?->name,
        ])->setPaper('a4');
    }

    /** Nom de fichier stable et triable, sans caractere interdit par Windows. */
    public function filenameFor(Invoice $invoice): string
    {
        $reference = preg_replace('/[^A-Za-z0-9._-]+/', '-', $invoice->reference) ?? 'facture';

        return "facture-{$reference}.pdf";
    }

    private function chipFor(?MatchStatus $status): string
    {
        return match ($status) {
            MatchStatus::Matched => 'chip-ok',
            MatchStatus::PartiallyMatched => 'chip-warn',
            null => 'chip-block',
            default => 'chip-block',
        };
    }
}
