<?php

declare(strict_types=1);

namespace Atlas\Resources;

/**
 * Development-instance helpers: the paired test instance, test-user cleanup and testing tokens.
 */
final class Testing extends Resource
{
    /**
     * `POST /v1/test_instance` — get (creating on first call) the paired development instance. Pass
     * rotate_secret: true to mint a fresh secret key.
     *
     * @param array<string,mixed> $body
     *
     * @return array<string,mixed>
     */
    public function testInstance(array $body = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request('POST', '/v1/test_instance', body: $body, idempotencyKey: $idempotencyKey);
    }

    /**
     * `POST /v1/test_users/purge` — delete test users in bulk (development instances only). Loop until
     * has_more is false.
     *
     * @param array<string,mixed> $body
     *
     * @return array<string,mixed>
     */
    public function purgeTestUsers(array $body = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request('POST', '/v1/test_users/purge', body: $body, idempotencyKey: $idempotencyKey);
    }

    /**
     * `POST /v1/testing_tokens` — mint a short-lived testing token (development instances only).
     *
     * @return array<string,mixed>
     */
    public function createTestingToken(): array
    {
        return $this->http->request('POST', '/v1/testing_tokens');
    }
}
