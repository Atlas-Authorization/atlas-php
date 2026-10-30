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

    /**
     * `POST /v1/testing/sign_in_url` — a ready one-time URL that signs a browser in as `$userId` and
     * lands on `$redirectUrl` (an allowed origin or localhost). Development instances only (403
     * `TESTING_UNAVAILABLE` otherwise).
     *
     * @return array<string,mixed> `{object: 'testing_sign_in_url', id, user_id, ticket, url, redirect_url, expires_in, expires_at}`
     */
    public function createSignInUrl(string $userId, string $redirectUrl, ?int $expiresInSeconds = null): array
    {
        return $this->http->request('POST', '/v1/testing/sign_in_url', body: array_filter([
            'user_id' => $userId,
            'redirect_url' => $redirectUrl,
            'expires_in_seconds' => $expiresInSeconds,
        ], static fn ($v) => $v !== null));
    }
}
