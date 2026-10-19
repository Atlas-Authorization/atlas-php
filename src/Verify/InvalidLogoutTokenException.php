<?php

declare(strict_types=1);

namespace Atlas\Verify;

use Atlas\AtlasException;

/**
 * Thrown by {@see BackchannelLogout::verify()} for a logout token that must be
 * refused. Answer the request with 400, or with 503 and `Retry-After` when
 * `$retryable` is true (Atlas then retries the delivery; it never retries a 400).
 *
 * `$reason` is a coarse code: `malformed`, `no_keys`, `invalid_signature`,
 * `wrong_type`, `wrong_issuer`, `wrong_audience`, `expired`,
 * `issued_in_future`, `missing_claim`, `invalid_claim`, `nonce_present`,
 * `replayed`.
 */
final class InvalidLogoutTokenException extends AtlasException
{
    /**
     * True when the failure is temporary and on your side: `no_keys`, the
     * issuer's keys could not be fetched (a network error or a JWKS outage).
     */
    public readonly bool $retryable;

    public function __construct(public readonly string $reason, ?string $message = null)
    {
        $this->retryable = $reason === 'no_keys';
        parent::__construct($message ?? "Invalid logout token ({$reason}).", $this->retryable ? 503 : 400);
    }
}
