<?php

declare(strict_types=1);

namespace Atlas\Auth;

use Atlas\AtlasException;

/**
 * An OAuth device-flow / token-exchange step failed. `$error` is the OAuth error code (`access_denied`, `expired_token`,
 * `invalid_grant`, `not_configured`, ...); the exception code is the HTTP status.
 */
final class OAuthFlowException extends AtlasException
{
    public function __construct(public readonly string $error, string $description, int $status)
    {
        parent::__construct($description, $status);
    }
}
