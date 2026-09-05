<?php

namespace App\Domains\Invoicing\Exceptions;

use App\Domains\Shared\Exceptions\DomainException;

final class InvoiceLineNotOnPurchaseOrderException extends DomainException
{
    public static function make(string $purchaseOrderLineId, string $purchaseOrderReference): self
    {
        return new self(
            message: "La ligne de commande #{$purchaseOrderLineId} n appartient pas au bon de commande {$purchaseOrderReference}.",
            errorCode: 'invoice_line_not_on_purchase_order',
            statusCode: 422,
            context: [
                'purchase_order_line_id' => $purchaseOrderLineId,
                'purchase_order_reference' => $purchaseOrderReference,
            ],
        );
    }
}
