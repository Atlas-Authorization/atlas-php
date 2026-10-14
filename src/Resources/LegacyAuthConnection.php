<?php

declare(strict_types=1);

namespace Atlas\Resources;

/**
 * `/v1/legacy_auth_connection` — lazy migration from a legacy user store at sign-in.
 */
final class LegacyAuthConnection extends Resource
{
    /**
     * `GET /v1/legacy_auth_connection` — the lazy-migration connection (configured: false when none).
     * Never the signing secret.
     *
     * @return array<string,mixed>
     */
    public function get(): array
    {
        return $this->http->request('GET', '/v1/legacy_auth_connection');
    }

    /**
     * `PUT /v1/legacy_auth_connection` — create or replace the lazy-migration connection (verify_url,
     * signing_secret, enabled, import_on_login, timeout_ms).
     *
     * @param array<string,mixed> $body
     *
     * @return array<string,mixed>
     */
    public function set(array $body, ?string $idempotencyKey = null): array
    {
        return $this->http->request('PUT', '/v1/legacy_auth_connection', body: $body, idempotencyKey: $idempotencyKey);
    }

    /**
     * `DELETE /v1/legacy_auth_connection` — delete the lazy-migration connection.
     *
     * @return array<string,mixed>
     */
    public function delete(?string $idempotencyKey = null): array
    {
        return $this->http->request('DELETE', '/v1/legacy_auth_connection', idempotencyKey: $idempotencyKey);
    }
}
