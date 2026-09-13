<?php

declare(strict_types=1);

namespace Atlas\Resources;

/** `/v1/api_keys` — end-user/organization API keys a tenant mints and verifies. */
final class ApiKeys extends Resource
{
    /**
     * Optionally narrowed to one subject.
     *
     * @param array{subject_type?:string,subject_id?:string} $query
     *
     * @return array<string,mixed> a ListPage of keys
     */
    public function list(array $query = []): array
    {
        return $this->http->request('GET', '/v1/api_keys', query: $query);
    }

    /**
     * Mint a key. The secret is returned once, here.
     *
     * @param array{subject_type:string,subject_id:string,name?:string,claims?:array<string,mixed>,expires_at?:?int} $body
     *
     * @return array<string,mixed>
     */
    public function create(array $body, ?string $idempotencyKey = null): array
    {
        return $this->http->request('POST', '/v1/api_keys', body: $body, idempotencyKey: $idempotencyKey);
    }

    /**
     * Check a presented secret. Rate-limited — it is an online credential check.
     *
     * @return array<string,mixed> `{ valid: false }` or `{ valid: true, ... }`
     */
    public function verify(string $secret): array
    {
        return $this->http->request('POST', '/v1/api_keys/verify', body: ['secret' => $secret]);
    }

    /** @return array<string,mixed> */
    public function get(string $id): array
    {
        return $this->http->request('GET', '/v1/api_keys/' . rawurlencode($id));
    }

    /**
     * @param array{name?:?string,claims?:array<string,mixed>,expires_at?:?int} $body
     *
     * @return array<string,mixed>
     */
    public function update(string $id, array $body): array
    {
        return $this->http->request('PATCH', '/v1/api_keys/' . rawurlencode($id), body: $body);
    }

    /** Revoke a key. It stays queryable but never authenticates again. @return array<string,mixed> */
    public function delete(string $id): array
    {
        return $this->http->request('DELETE', '/v1/api_keys/' . rawurlencode($id));
    }
}
