<?php

namespace App\Domains\Matching\Contracts;

use App\Domains\Matching\Enums\DiscrepancyType;

/**
 * Ecarts arbitres favorablement par un humain, a re-appliquer lors des
 * rapprochements suivants de la meme facture.
 */
interface ApprovedOverrideReaderContract
{
    /**
     * @return array<int, list<DiscrepancyType>>
     *                                           invoice_line_id => types d'ecart acceptes
     */
    public function approvedOverridesForInvoice(int $invoiceId): array;
}
