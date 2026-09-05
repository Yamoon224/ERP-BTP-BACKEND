<?php

namespace App\Domains\Users\Exceptions;

use App\Domains\Shared\Exceptions\DomainException;
use App\Models\User;

final class UserNotDeletableException extends DomainException
{
    public static function self(User $user): self
    {
        return new self(
            message: 'Vous ne pouvez pas supprimer votre propre compte.',
            errorCode: 'cannot_delete_self',
            statusCode: 409,
            context: ['user_id' => $user->id],
        );
    }

    public static function lastAdmin(User $user): self
    {
        return new self(
            message: "Ce compte est le dernier administrateur : le supprimer rendrait l'application inadministrable.",
            errorCode: 'last_administrator',
            statusCode: 409,
            context: ['user_id' => $user->id],
        );
    }
}
