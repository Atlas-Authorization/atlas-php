<?php

declare(strict_types=1);

namespace Atlas\Verify;

/**
 * Hands out the server nonce a DPoP proof must carry (RFC 9449 §8) and checks
 * one. `current()` goes to the client in the `DPoP-Nonce` header; `valid()`
 * accepts the nonces still in force.
 */
interface DpopNonceSource
{
    public function current(): string;

    public function valid(string $nonce): bool;
}
