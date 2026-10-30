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
     * Mint a key. The secret is returned once, here. Pass `limits` to set per-key spend caps, metered
     * with {@see recordUsage()}. The returned key carries `limits` and `usage` (each cap with its
     * current-window `used`, `remaining`, `window_start` and `resets_at`).
     * `scopes` sets the key's scopes; `import` brings an existing key over by its hash (`secret_hash`,
     * `hash_algorithm` `sha256` or `sha256_hex`, `prefix`), the response has no `secret`, and the original secret verifies from then on.
     *
     * @param array{subject_type:string,subject_id:string,name?:string,claims?:array<string,mixed>,expires_at?:?int,scopes?:list<string>,import?:array{secret_hash:string,hash_algorithm?:'sha256'|'sha256_hex',prefix:string},limits?:?list<array{unit:string,amount:int,period:'request'|'second'|'minute'|'day'|'month'|'total'}>} $body
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
     * @return array<string,mixed> `{ valid: false }` or `{ valid: true, ... }` — a valid result carries the
     *                             subject, `name`, `claims`, `scopes`, `constraints`, `limits`, `usage`
     *                             and `effective_scopes`
     */
    public function verify(string $secret, ?array $requiredScopes = null): array
    {
        $body = ['secret' => $secret];
        if ($requiredScopes !== null) {
            $body['required_scopes'] = $requiredScopes;
        }

        return $this->http->request('POST', '/v1/api_keys/verify', body: $body);
    }

    /** @return array<string,mixed> */
    public function get(string $id): array
    {
        return $this->http->request('GET', '/v1/api_keys/' . rawurlencode($id));
    }

    /**
     * Change a key. `limits: null` removes every spend cap.
     *
     * @param array{name?:?string,claims?:array<string,mixed>,expires_at?:?int,limits?:?list<array{unit:string,amount:int,period:'request'|'second'|'minute'|'day'|'month'|'total'}>} $body
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

    /**
     * `GET /v1/api_keys/revoked` — keys revoked since a cursor (since), for deny-listing revoked key ids.
     *
     * @param array<string,mixed> $params query parameters
     *
     * @return array<string,mixed>
     */
    public function listRevoked(array $params = []): array
    {
        return $this->http->request('GET', '/v1/api_keys/revoked', query: $params);
    }

    /**
     * `POST /v1/api_keys/:id/usage` — meter usage against the key's spend caps, atomically. When the
     * debit fits every cap it is recorded and `allowed` is true; when it would exceed a cap nothing is
     * recorded and the result is `allowed: false` with `code: "LIMIT_EXCEEDED"` (a 200, not an
     * exception). `$idempotencyKey` is sent in the body: the same key returns the first outcome without
     * counting again (`idempotent_replay: true`); reusing it with a different unit or amount throws a
     * 409 `IDEMPOTENCY_CONFLICT`.
     *
     * @return array{object:'api_key_usage_debit',key_id:string,unit:string,amount:int,allowed:bool,code?:string,exceeded:list<array<string,mixed>>,usage:list<array<string,mixed>>,counters:list<array<string,mixed>>,idempotent_replay:bool}
     */
    public function recordUsage(string $id, int $amount, string $unit, ?string $idempotencyKey = null): array
    {
        $body = ['amount' => $amount, 'unit' => $unit];
        if ($idempotencyKey !== null) {
            $body['idempotency_key'] = $idempotencyKey;
        }
        return $this->http->request('POST', '/v1/api_keys/' . rawurlencode($id) . '/usage', body: $body);
    }

    /**
     * `GET /v1/api_keys/:id/usage` — each cap with used/remaining/resets_at, plus the day, month and
     * total counters of every unit the key has been debited in.
     *
     * @return array{object:'api_key_usage',key_id:string,limits:?list<array<string,mixed>>,usage:list<array<string,mixed>>,counters:list<array<string,mixed>>}
     */
    public function getUsage(string $id): array
    {
        return $this->http->request('GET', '/v1/api_keys/' . rawurlencode($id) . '/usage');
    }

    /**
     * `POST /v1/api_keys/token` — mint a short-lived access JWT for an API key (secret, ttl_seconds,
     * audience).
     *
     * @param array<string,mixed> $body
     *
     * @return array<string,mixed>
     */
    public function mintToken(array $body): array
    {
        return $this->http->request('POST', '/v1/api_keys/token', body: $body);
    }
}
