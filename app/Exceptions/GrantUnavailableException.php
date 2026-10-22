<?php

namespace App\Exceptions;

/**
 * Thrown when a grant cannot be opened: pending/denied/revoked/expired,
 * expired key in Redis, or a bundle that no longer decrypts.
 */
class GrantUnavailableException extends ApiException
{
    public static function make(): self
    {
        return new self(
            'GRANT_UNAVAILABLE',
            'Grant không còn khả dụng (đã bị từ chối, thu hồi hoặc hết hạn).',
            410,
        );
    }
}
