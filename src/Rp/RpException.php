<?php

declare(strict_types=1);

namespace Atlas\Rp;

use Atlas\AtlasException;

/**
 * A sign-in step failed. `$error` is a stable code: `state_missing`, `state_mismatch`, `state_expired`, `access_denied` (and any
 * other `error` the issuer returned), `token_exchange_failed`, `id_token_invalid`, `nonce_mismatch`, `discovery_failed`,
 * `not_confidential`.
 */
final class RpException extends AtlasException
{
    public function __construct(public readonly string $error, string $message, int $status = 400)
    {
        parent::__construct($message, $status);
    }
}
