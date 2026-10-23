<?php

declare(strict_types=1);

namespace Atlas\PolicySource;

/**
 * A signed policy source event: send `$body` byte-for-byte to the source's `webhook_url`, with
 * `$headers` and `Content-Type: application/json`.
 */
final class SignedPolicySourceEvent
{
    /**
     * @param array<string,string> $headers
     */
    public function __construct(
        public readonly string $body,
        public readonly array $headers,
    ) {
    }
}
