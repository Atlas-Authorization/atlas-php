<?php

declare(strict_types=1);

namespace Atlas\Verify;

/**
 * The verified claims of a back-channel logout token. End the local session(s)
 * matching `$sid` (one session) or, when `$sid` is null, every session of `$sub`.
 */
final class LogoutTokenClaims
{
    /**
     * @param array<string,mixed> $claims every claim in the token
     */
    public function __construct(
        public readonly ?string $sub,
        public readonly ?string $sid,
        public readonly string $jti,
        public readonly int $iat,
        public readonly array $claims,
    ) {
    }
}
