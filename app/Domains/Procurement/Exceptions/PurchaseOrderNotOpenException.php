<?php

namespace App\Domains\Procurement\Exceptions;

use App\Domains\Procurement\Enums\PurchaseOrderStatus;
use App\Domains\Shared\Exceptions\DomainException;

final class PurchaseOrderNotOpenException extends DomainException
{
    public static function make(string $reference, PurchaseOrderStatus $status): self
    {
        return new self(
            message: "Le bon de commande {$reference} est {$status->label()} : il n accepte plus de nouveaux documents.",
            errorCode: 'purchase_order_not_open',
            statusCode: 409,
            context: ['reference' => $reference, 'status' => $status->value],
        );
    }
}
