<?php

namespace App\Domains\Invoicing\Contracts;

use App\Domains\Invoicing\Enums\InvoiceStatus;
use App\Models\Invoice;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface InvoiceRepositoryContract
{
    /** @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Invoice>
     */
    public function paginate(array $filters = [], int $perPage = 10): LengthAwarePaginator;

    public function findOrFail(string $id): Invoice;

    /** Charge la facture avec ses lignes et le PO complet, pret pour le moteur. */
    public function findForMatchingOrFail(string $id): Invoice;

    /**
     * @param  array<string, mixed>  $attributes
     * @param  list<array<string, mixed>>  $lines
     */
    public function create(array $attributes, array $lines): Invoice;

    public function updateStatus(Invoice $invoice, InvoiceStatus $status): Invoice;

    /**
     * Corrige des attributs d'en-tete de la facture (devise, echeance…).
     *
     * Volontairement distinct de `updateStatus` : le statut est derive du
     * moteur, ces attributs-la viennent d'une saisie humaine, et les deux ne
     * doivent pas pouvoir se confondre a l'appel.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function updateAttributes(Invoice $invoice, array $attributes): Invoice;

    public function existsForSupplierReference(string $supplierId, string $reference): bool;
}
