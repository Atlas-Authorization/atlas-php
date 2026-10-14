<?php

declare(strict_types=1);

namespace Atlas\Verify;

use Atlas\AtlasException;

/**
 * Thrown by {@see BackchannelLogout::verify()} for a logout token that must be
 * refused. Answer the request with 400.
 *
 * `$reason` is a coarse code: `malformed`, `no_keys`, `invalid_signature`,
 * `wrong_type`, `wrong_issuer`, `wrong_audience`, `expired`,
 * `issued_in_future`, `missing_claim`, `invalid_claim`, `nonce_present`,
 * `replayed`.
 */
final class InvalidLogoutTokenException extends AtlasException
{
    public function __construct(public readonly string $reason, ?string $message = null)
    {
        parent::__construct($message ?? "Invalid logout token ({$reason}).", 400);
    }
}
