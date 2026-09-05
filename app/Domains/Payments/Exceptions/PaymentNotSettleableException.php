<?php

namespace App\Domains\Payments\Exceptions;

use App\Domains\Shared\Exceptions\DomainException;
use App\Models\PaymentAuthorization;

final class PaymentNotSettleableException extends DomainException
{
    public static function alreadySettled(PaymentAuthorization $authorization): self
    {
        return new self(
            message: "Cette autorisation a deja ete reglee le {$authorization->settled_at?->format('d/m/Y')} (reference {$authorization->payment_reference}).",
            errorCode: 'payment_already_settled',
            statusCode: 409,
            context: [
                'payment_authorization_id' => $authorization->id,
                'settled_at' => $authorization->settled_at?->toIso8601String(),
            ],
        );
    }

    public static function notActive(PaymentAuthorization $authorization): self
    {
        return new self(
            message: "Cette autorisation n'est plus active ({$authorization->status->label()}) : elle ne peut pas donner lieu a un reglement.",
            errorCode: 'payment_not_active',
            statusCode: 409,
            context: [
                'payment_authorization_id' => $authorization->id,
                'status' => $authorization->status->value,
            ],
        );
    }
}
