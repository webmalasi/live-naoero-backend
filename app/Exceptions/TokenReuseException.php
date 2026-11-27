<?php

namespace App\Exceptions;

/**
 * A refresh token that was already rotated was presented again: it leaked.
 * Thrown out of the refresh transaction (which rolls back), so the caller
 * revokes the whole session outside it.
 */
class TokenReuseException extends ApiException
{
    public function __construct(
        public readonly string $familyId,
        public readonly string $customerId,
    ) {
        parent::__construct(401, 'session_expired', 'Your session has ended. Please log in again.');
    }
}
