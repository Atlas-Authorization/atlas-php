<?php

declare(strict_types=1);

namespace Atlas\Handshake;

/**
 * The fresh session a satellite property receives when it redeems a
 * cross-property handshake nonce — the PHP twin of the TypeScript
 * `HandshakeSession`.
 *
 * The tokens arrive in the redeem RESPONSE BODY (never a URL), and the caller
 * sets them as its OWN first-party cookies on the satellite domain:
 *
 *   - {@see $jwt}          → `__session`   (script-readable, short-lived)
 *   - {@see $refreshToken} → `__atlas_rt`  (HttpOnly)
 *
 * @see Handshake::redeemHandshake()
 */
final class HandshakeSession
{
    public function __construct(
        /** The `__session` JWT value to set as a cookie (script-readable, short-lived). */
        public readonly string $jwt,
        /** The `__atlas_rt` refresh value to set as an HttpOnly cookie. */
        public readonly string $refreshToken,
        public readonly string $sessionId,
        /** Seconds until the `__session` JWT expires. */
        public readonly int $expiresIn,
    ) {
    }
}
