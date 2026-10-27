<?php

declare(strict_types=1);

namespace Atlas\Resources;

use Atlas\Http;

/**
 * `/v1/vault/objects` - 7 operations.
 */
final class Vault extends Resource
{
    public readonly VaultKeyProvider $keyProvider;

    public function __construct(Http $http)
    {
        parent::__construct($http);
        $this->keyProvider = new VaultKeyProvider($http);
    }

    /**
     * `POST /v1/vault/objects` - Store a secret. `key_context` (1–10 string labels) is mandatory and is
     * authenticated into the encryption: the value can only be opened with the context it was written
     * under. The value (up to 64 KiB) is sealed under a fresh data key wrapped by the instance key, or by
     * your own cloud KMS key when one is configured. 409 when the name is taken.
     *
     * @param array{name?:string,value?:string,key_context?:array<string,mixed>,metadata?:array<string,mixed>} $body
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>
     */
    public function create(array $body = [], array $params = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'POST',
            '/v1/vault/objects',
            body: $body,
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * `GET /v1/vault/objects` - List vault objects (metadata only, never values), newest first. Filter by
     * `name_prefix` or by `key_context` (a JSON object; objects whose context contains every given label).
     *
     * @param array{name_prefix?:string,key_context?:string,starting_after?:string,limit?:int} $params
     *
     * @return array<string,mixed>
     */
    public function list(array $params = []): array
    {
        return $this->http->request('GET', '/v1/vault/objects', query: $params);
    }

    /**
     * `GET /v1/vault/objects/:id/metadata` - Read a vault object's metadata without its value.
     *
     * @return array<string,mixed>
     */
    public function metadata(string $id): array
    {
        return $this->http->request('GET', '/v1/vault/objects/' . rawurlencode($id) . '/metadata');
    }

    /**
     * `GET /v1/vault/objects/:id/versions` - List the versions of a vault object (number, which key
     * protects it, when written) — never values.
     *
     * @param array{limit?:int,starting_after?:string} $params
     *
     * @return array<string,mixed>
     */
    public function versions(string $id, array $params = []): array
    {
        return $this->http->request('GET', '/v1/vault/objects/' . rawurlencode($id) . '/versions', query: $params);
    }

    /**
     * `GET /v1/vault/objects/:id` - Read a vault object with its decrypted value (the current version, or
     * `version`). Every read is audited. 409 if the version's wrapping key provider is no longer
     * configured; 502 VAULT_KMS_ERROR when your KMS refuses.
     *
     * @param array{version?:int} $params
     *
     * @return array<string,mixed>
     */
    public function read(string $id, array $params = []): array
    {
        return $this->http->request('GET', '/v1/vault/objects/' . rawurlencode($id), query: $params);
    }

    /**
     * `PUT /v1/vault/objects/:id` - Append a new version with a new value. Earlier versions stay readable.
     * Pass `version_check` (the version you last read) for optimistic concurrency: 409 if another update
     * landed since.
     *
     * @param array{value?:string,version_check?:int|float} $body
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>
     */
    public function update(string $id, array $body = [], array $params = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'PUT',
            '/v1/vault/objects/' . rawurlencode($id),
            body: $body,
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * `DELETE /v1/vault/objects/:id` - Delete a vault object and every version of it. Irreversible.
     *
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>
     */
    public function delete(string $id, array $params = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'DELETE',
            '/v1/vault/objects/' . rawurlencode($id),
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }
}
