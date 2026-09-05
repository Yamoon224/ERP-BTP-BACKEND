<?php

namespace App\Domains\Invoicing\Services;

use App\Domains\Invoicing\Contracts\InvoiceRepositoryContract;
use App\Domains\Invoicing\Enums\InvoiceStatus;
use App\Domains\Invoicing\Exceptions\DuplicateInvoiceException;
use App\Domains\Invoicing\Exceptions\InvoiceCurrencyNotChangeableException;
use App\Domains\Invoicing\Exceptions\InvoiceLineNotOnPurchaseOrderException;
use App\Domains\Invoicing\Exceptions\InvoiceNotEditableException;
use App\Domains\Matching\Services\InvoiceMatchingService;
use App\Domains\Payments\Contracts\PaymentAuthorizationRepositoryContract;
use App\Domains\Procurement\Contracts\PurchaseOrderRepositoryContract;
use App\Domains\Procurement\Exceptions\PurchaseOrderNotOpenException;
use App\Domains\Shared\Enums\Currency;
use App\Models\Invoice;
use App\Models\PurchaseOrder;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * Réception des factures fournisseur.
 *
 * Une facture soumise est immédiatement rapprochée : le contrôle à 3 voies est
 * la porte d'entrée du circuit de paiement, pas une étape optionnelle qu'on
 * penserait à déclencher plus tard.
 */
final class InvoiceService
{
    public function __construct(
        private readonly InvoiceRepositoryContract $invoices,
        private readonly PurchaseOrderRepositoryContract $purchaseOrders,
        private readonly InvoiceMatchingService $matchingService,
        private readonly PaymentAuthorizationRepositoryContract $authorizations,
    ) {}

    /** @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Invoice>
     */
    public function list(array $filters, int $perPage = 10): LengthAwarePaginator
    {
        return $this->invoices->paginate($filters, $perPage);
    }

    public function find(int $id): Invoice
    {
        return $this->invoices->findOrFail($id);
    }

    /**
     * @param  array{reference: string, purchase_order_id: int, currency?: string|null, invoice_date: string, due_date?: string|null, lines: list<array<string, mixed>>}  $data
     *
     * @throws DuplicateInvoiceException
     * @throws PurchaseOrderNotOpenException
     * @throws InvoiceLineNotOnPurchaseOrderException
     */
    public function submit(array $data, User $creator): Invoice
    {
        $purchaseOrder = $this->purchaseOrders->findWithLinesOrFail($data['purchase_order_id']);

        if (! $purchaseOrder->status->acceptsDocuments()) {
            throw PurchaseOrderNotOpenException::make($purchaseOrder->reference, $purchaseOrder->status);
        }

        if ($this->invoices->existsForSupplierReference($purchaseOrder->supplier_id, $data['reference'])) {
            throw DuplicateInvoiceException::make($data['reference'], $purchaseOrder->supplier_id);
        }

        $this->assertLinesBelongToPurchaseOrder($purchaseOrder, $data['lines']);

        return DB::transaction(function () use ($data, $purchaseOrder, $creator): Invoice {
            $invoice = $this->invoices->create([
                'reference' => $data['reference'],
                // Le fournisseur vient du PO, jamais du corps de la requête :
                // c'est la garantie que la comparaison des fournisseurs porte
                // sur une donnée que l'émetteur de la facture ne choisit pas.
                'supplier_id' => $purchaseOrder->supplier_id,
                'purchase_order_id' => $purchaseOrder->id,
                'currency' => strtoupper($data['currency'] ?? $purchaseOrder->currency),
                'status' => InvoiceStatus::Received,
                'invoice_date' => $data['invoice_date'],
                'due_date' => $data['due_date'] ?? null,
                'created_by' => $creator->id,
            ], $this->numberLines($data['lines']));

            $this->matchingService->match($invoice, null, InvoiceMatchingService::TRIGGER_INVOICE_SUBMITTED);

            return $this->invoices->findOrFail($invoice->id);
        });
    }

    /**
     * Change la devise de reglement d'une facture, puis rejoue le contrôle.
     *
     * Le rapprochement n'est pas une consequence optionnelle du changement :
     * c'est lui qui donne son sens a la nouvelle devise. Tant qu'il n'a pas
     * tourne, le montant autorise au paiement resterait exprime dans l'ancienne
     * unite — un chiffre juste dans la mauvaise monnaie, ce qui est pire qu'un
     * chiffre absent.
     *
     * Les montants des lignes ne sont **pas** convertis : changer la devise
     * corrige la facon dont la facture a ete lue, pas ce que le fournisseur a
     * ecrit dessus. Convertir silencieusement reviendrait a fabriquer des
     * montants que personne n'a jamais factures.
     *
     * @throws InvoiceCurrencyNotChangeableException
     */
    public function changeCurrency(Invoice $invoice, string $currency, ?User $actor = null): Invoice
    {
        if ($invoice->status === InvoiceStatus::Cancelled) {
            throw InvoiceCurrencyNotChangeableException::becauseCancelled($invoice->reference);
        }

        $authorization = $this->authorizations->activeForInvoice($invoice->id);

        if ($authorization?->settled_at !== null) {
            throw InvoiceCurrencyNotChangeableException::becauseSettled($invoice->reference);
        }

        $target = Currency::from(strtoupper($currency));

        if ($invoice->currency === $target) {
            return $this->invoices->findOrFail($invoice->id);
        }

        return DB::transaction(function () use ($invoice, $target, $actor): Invoice {
            $updated = $this->invoices->updateAttributes($invoice, ['currency' => $target]);

            $this->matchingService->match(
                $updated,
                $actor,
                InvoiceMatchingService::TRIGGER_CURRENCY_CHANGED,
            );

            return $this->invoices->findOrFail($invoice->id);
        });
    }

    /** @throws InvoiceNotEditableException */
    public function cancel(Invoice $invoice): Invoice
    {
        if ($invoice->status === InvoiceStatus::Cancelled) {
            throw InvoiceNotEditableException::alreadyCancelled($invoice->reference);
        }

        return DB::transaction(function () use ($invoice): Invoice {
            // Annuler libère les quantités que cette facture retenait sur le PO
            // et retire tout droit à paiement.
            $this->authorizations->revokeActiveForInvoice($invoice->id);

            return $this->invoices->updateStatus($invoice, InvoiceStatus::Cancelled);
        });
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     *
     * @throws InvoiceLineNotOnPurchaseOrderException
     */
    private function assertLinesBelongToPurchaseOrder(PurchaseOrder $purchaseOrder, array $lines): void
    {
        $validIds = $purchaseOrder->lines->pluck('id')->all();

        foreach ($lines as $line) {
            $lineId = $line['purchase_order_line_id'] ?? null;

            // Une ligne sans rattachement est acceptée volontairement : le
            // moteur doit pouvoir la signaler comme écart. En revanche, une
            // ligne rattachée à un AUTRE bon de commande est un refus net.
            if ($lineId !== null && ! in_array((int) $lineId, $validIds, true)) {
                throw InvoiceLineNotOnPurchaseOrderException::make((int) $lineId, $purchaseOrder->reference);
            }
        }
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     * @return list<array<string, mixed>>
     */
    private function numberLines(array $lines): array
    {
        return array_map(
            fn (array $line, int $index): array => [
                'line_number' => $index + 1,
                'purchase_order_line_id' => $line['purchase_order_line_id'] ?? null,
                'description' => $line['description'],
                'quantity' => $line['quantity'],
                'unit_price' => $line['unit_price'],
            ],
            $lines,
            array_keys($lines),
        );
    }
}
