<?php

namespace App\Domains\Matching\Exceptions;

use App\Domains\Matching\Enums\ReviewStatus;
use App\Domains\Shared\Exceptions\DomainException;

final class ExceptionAlreadyReviewedException extends DomainException
{
    public static function make(int $exceptionId, ReviewStatus $currentStatus): self
    {
        return new self(
            message: "Cet ecart a deja ete arbitre ({$currentStatus->label()}). Relancez un rapprochement pour produire un nouvel ecart.",
            errorCode: 'exception_already_reviewed',
            statusCode: 409,
            context: ['match_exception_id' => $exceptionId, 'review_status' => $currentStatus->value],
        );
    }
}
