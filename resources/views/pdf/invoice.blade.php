{{--
    Facture exportee en PDF.

    Le document ne se contente pas de recopier la facture : il porte le verdict
    du rapprochement et le montant reellement autorise au paiement. C'est ce qui
    en fait une piece utilisable en interne — une facture imprimee sans son
    controle ne dit pas si elle est payable, et c'est precisement la question
    que se pose celui qui l'imprime.

    Mise en page en tableaux et styles en ligne : Dompdf ne connait ni flexbox
    ni grid, et une feuille de style moderne y rendrait n'importe comment.
--}}
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Facture {{ $invoice->reference }}</title>
    <style>
        @page { margin: 26mm 16mm 22mm 16mm; }

        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 10px;
            color: #0f172a;
            margin: 0;
        }

        .muted { color: #64748b; }
        .right { text-align: right; }
        .nums  { font-variant-numeric: tabular-nums; }

        .band {
            background-color: #2563eb;
            color: #ffffff;
            padding: 10px 12px;
        }
        .band h1 { margin: 0; font-size: 15px; letter-spacing: .04em; }
        .band p  { margin: 2px 0 0; font-size: 9px; color: #dbeafe; }

        table { width: 100%; border-collapse: collapse; }

        .meta td { vertical-align: top; padding: 10px 0 0; width: 50%; }
        .meta .label {
            font-size: 8px; text-transform: uppercase; letter-spacing: .08em; color: #64748b;
        }
        .meta .value { font-size: 11px; font-weight: bold; }

        .lines th {
            background-color: #1d4ed8; color: #ffffff;
            font-size: 8px; text-transform: uppercase; letter-spacing: .06em;
            padding: 6px 8px; text-align: left;
        }
        .lines td { padding: 6px 8px; border-bottom: 1px solid #e2e8f0; }
        .lines tr:nth-child(even) td { background-color: #f8fafc; }

        .totals td { padding: 4px 8px; }
        .totals .grand { font-size: 12px; font-weight: bold; border-top: 2px solid #1d4ed8; }

        .verdict { border: 1px solid #e2e8f0; padding: 10px 12px; margin-top: 14px; }
        .verdict h2 { margin: 0 0 6px; font-size: 10px; text-transform: uppercase; letter-spacing: .08em; }

        .chip {
            display: inline-block; padding: 2px 7px; font-size: 9px; font-weight: bold;
            border: 1px solid currentColor;
        }
        .chip-ok    { color: #15803d; }
        .chip-warn  { color: #b45309; }
        .chip-block { color: #be123c; }

        .footer {
            position: fixed; bottom: -14mm; left: 0; right: 0;
            font-size: 8px; color: #94a3b8; border-top: 1px solid #e2e8f0; padding-top: 4px;
        }
    </style>
</head>
<body>

<div class="band">
    <h1>FACTURE {{ $invoice->reference }}</h1>
    <p>ERP BTP — controle a 3 voies (bon de commande / bon de livraison / facture)</p>
</div>

<table class="meta">
    <tr>
        <td>
            <div class="label">Fournisseur</div>
            <div class="value">{{ $invoice->supplier?->name ?? '—' }}</div>
            <div class="muted">
                {{ $invoice->supplier?->code }}
                @if ($invoice->supplier?->vat_number)
                    · TVA {{ $invoice->supplier->vat_number }}
                @endif
            </div>
        </td>
        <td>
            <div class="label">Bon de commande</div>
            <div class="value">{{ $invoice->purchaseOrder?->reference ?? '—' }}</div>
            <div class="muted">
                Chantier : {{ $invoice->purchaseOrder?->project?->name ?? '—' }}
            </div>
        </td>
    </tr>
    <tr>
        <td>
            <div class="label">Date de facture</div>
            <div class="value">{{ $invoice->invoice_date->format('d/m/Y') }}</div>
            <div class="muted">
                Echeance : {{ $invoice->due_date?->format('d/m/Y') ?? '—' }}
            </div>
        </td>
        <td>
            <div class="label">Statut</div>
            <div class="value">{{ $invoice->status->label() }}</div>
            <div class="muted">Devise de reglement : {{ $currency->value }} ({{ $currency->symbol() }})</div>
        </td>
    </tr>
</table>

<table class="lines" style="margin-top: 16px;">
    <thead>
        <tr>
            <th style="width: 26px;">#</th>
            <th>Designation</th>
            <th class="right" style="width: 70px;">Quantite</th>
            <th class="right" style="width: 90px;">Prix unitaire</th>
            <th class="right" style="width: 95px;">Montant</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($invoice->lines as $line)
            <tr>
                <td class="nums">{{ $line->line_number }}</td>
                <td>
                    {{ $line->description }}
                    @if ($line->purchaseOrderLine)
                        <div class="muted">
                            Ligne BC {{ $line->purchaseOrderLine->line_number }}
                            · {{ $line->purchaseOrderLine->item_code }}
                        </div>
                    @else
                        <div class="muted">Aucune ligne de bon de commande rattachee</div>
                    @endif
                </td>
                <td class="right nums">{{ $formatQuantity($line->quantity) }}</td>
                <td class="right nums">{{ $formatMoney($line->unit_price) }}</td>
                <td class="right nums">{{ $formatMoney($line->quantity * $line->unit_price) }}</td>
            </tr>
        @endforeach
    </tbody>
</table>

<table class="totals" style="margin-top: 10px;">
    <tr>
        <td></td>
        <td class="right" style="width: 130px;">Total facture</td>
        <td class="right nums grand" style="width: 110px;">{{ $formatMoney($invoice->total_amount) }}</td>
    </tr>
</table>

@if ($matchRun)
    <div class="verdict">
        <h2>Verdict du rapprochement</h2>

        <table>
            <tr>
                <td style="width: 50%;">
                    <span class="chip {{ $verdictChip }}">{{ $matchRun->status->label() }}</span>
                    <div class="muted" style="margin-top: 6px;">
                        Exécution n°{{ $matchRun->id }} du
                        {{ $matchRun->evaluated_at?->format('d/m/Y H:i') }}
                        — moteur v{{ $matchRun->engine_version }}
                    </div>
                    <div class="muted">
                        Decidee par : {{ $matchRun->actor?->name ?? 'Moteur de rapprochement' }}
                    </div>
                </td>
                <td>
                    <table>
                        <tr>
                            <td class="muted">Montant autorise au paiement</td>
                            <td class="right nums">{{ $formatMoney($matchRun->matched_amount) }}</td>
                        </tr>
                        <tr>
                            <td class="muted">Montant non couvert</td>
                            <td class="right nums">{{ $formatMoney($matchRun->unmatched_amount) }}</td>
                        </tr>
                        <tr>
                            <td class="muted">Ecarts a arbitrer</td>
                            <td class="right nums">{{ $matchRun->exception_count }}</td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>

        <p class="muted" style="margin: 8px 0 0;">
            Seule la portion couverte simultanement par une quantite commandee, une quantite
            receptionnee et un prix conforme au bon de commande ouvre un droit a paiement.
            Toute portion non couverte reste bloquee jusqu'a arbitrage humain.
        </p>
    </div>
@else
    <div class="verdict">
        <h2>Verdict du rapprochement</h2>
        <p class="muted" style="margin: 0;">
            Cette facture n'a pas encore ete rapprochee : aucun montant n'est autorise au paiement.
        </p>
    </div>
@endif

<div class="footer">
    Document généré le {{ $generatedAt }}@if ($generatedBy) par {{ $generatedBy }}@endif —
    piece interne de controle, non contractuelle.
</div>

</body>
</html>
