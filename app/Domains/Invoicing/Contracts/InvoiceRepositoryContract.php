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

    public function findOrFail(int $id): Invoice;

    /** Charge la facture avec ses lignes et le PO complet, pret pour le moteur. */
    public function findForMatchingOrFail(int $id): Invoice;

    /**
     * @param  array<string, mixed>  $attributes
     * @param  list<array<string, mixed>>  $lines
     */
    public function create(array $attributes, array $lines): Invoice;

    public function updateStatus(Invoice $invoice, InvoiceStatus $status): Invoice;

    public function existsForSupplierReference(int $supplierId, string $reference): bool;
}
