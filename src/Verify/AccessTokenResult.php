<?php

declare(strict_types=1);

namespace Atlas\Verify;

/**
 * The verdict of {@see AccessTokenVerifier::verify()}. On success `ok` is true
 * and `claims`, `scopes`, `subject`, `clientId` and `dpopBound` are set.
 * Otherwise `status` (400, 401 or 403), `error` (null when no token was sent),
 * `description` and `wwwAuthenticate` (set it as the response header verbatim)
 * describe it. `dpopNonce`, when set, goes in the `DPoP-Nonce` response header
 * (on success and refusal alike).
 */
final class AccessTokenResult
{
    /**
     * @param array<string,mixed> $claims
     * @param list<string>        $scopes
     */
    public function __construct(
        public readonly bool $ok,
        public readonly int $status = 200,
        public readonly ?string $error = null,
        public readonly string $description = '',
        public readonly string $wwwAuthenticate = '',
        public readonly array $claims = [],
        public readonly array $scopes = [],
        public readonly string $subject = '',
        public readonly ?string $clientId = null,
        public readonly bool $dpopBound = false,
        public readonly ?string $dpopNonce = null,
    ) {
    }
}
