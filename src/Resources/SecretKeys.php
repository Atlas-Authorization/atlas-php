<?php

declare(strict_types=1);

namespace Atlas\Resources;

/**
 * `/v1/secret_keys` — secret keys with names, owners, expiry and IP allow-lists (`atlas_<env>_sk_…` or legacy `sk_…`).
 * The same routes are also served under `/v1/instance/secret_keys`.
 * The secret is shown once, on create and rotate. Pass `secret_recipient` (`x25519:<base64>`, see {@see \Atlas\Auth\SealedBox::generateKeyPair()}) in the create body to get it sealed to your key instead.
 */
final class SecretKeys extends Resource
{
    /**
     * Create a key: `{name?, owner?, expires_at?, allowed_origins?, allowed_ips?, scopes?, metadata?, external_ref?, secret_recipient?, key_format?: 'atlas'|'legacy'}`. `owner` defaults to the creator; `expires_at` defaults to one year and can never be longer (`null` is refused); `allowed_origins` locks the key to browser origins (exact, or one-level wildcard such as `https://*.example.com`): a call from another origin is refused with `ORIGIN_NOT_ALLOWED`. The secret is in the answer, once.
     *
     * @return array<string,mixed>
     */
    public function create(array $body, ?string $idempotencyKey = null): array
    {
        return $this->http->request('POST', '/v1/secret_keys', body: $body, idempotencyKey: $idempotencyKey);
    }

    /**
     * List the instance's secret keys (cursor-paginated; `starting_after`, `limit`).
     *
     * @return array<string,mixed>
     */
    public function list(array $query = []): array
    {
        return $this->http->request('GET', '/v1/secret_keys', query: $query);
    }

    /**
     * One key.
     *
     * @return array<string,mixed>
     */
    public function get(string $id): array
    {
        return $this->http->request('GET', '/v1/secret_keys/' . rawurlencode($id));
    }

    /**
     * Change `name`, `owner`, `expires_at` or `allowed_ips`.
     *
     * @return array<string,mixed>
     */
    public function update(string $id, array $body): array
    {
        return $this->http->request('PATCH', '/v1/secret_keys/' . rawurlencode($id), body: $body);
    }

    /**
     * Rotate: `{overlap_seconds?, key_format?}`. The new secret is shown once; the old key keeps working for the overlap.
     *
     * @return array<string,mixed>
     */
    public function rotate(string $id, ?array $body = null, ?string $idempotencyKey = null): array
    {
        return $this->http->request('POST', '/v1/secret_keys/' . rawurlencode($id) . '/rotate', body: $body, idempotencyKey: $idempotencyKey);
    }

    /**
     * Revoke a key at once.
     *
     * @return array<string,mixed>
     */
    public function revoke(string $id): array
    {
        return $this->http->request('POST', '/v1/secret_keys/' . rawurlencode($id) . '/revoke');
    }
}
