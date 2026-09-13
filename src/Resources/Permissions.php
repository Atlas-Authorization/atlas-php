<?php

declare(strict_types=1);

namespace Atlas\Resources;

/** `/v1/permissions` — custom permission definitions. */
final class Permissions extends Resource
{
    /** @return array<string,mixed> a ListPage of permissions */
    public function list(): array
    {
        return $this->http->request('GET', '/v1/permissions');
    }

    /**
     * @param array{key:string,name?:string,description?:string} $body
     *
     * @return array<string,mixed>
     */
    public function create(array $body, ?string $idempotencyKey = null): array
    {
        return $this->http->request('POST', '/v1/permissions', body: $body, idempotencyKey: $idempotencyKey);
    }

    /**
     * Relabel a custom permission (name/description only — the key is immutable).
     *
     * @param array{name?:string,description?:?string,key?:string} $body
     *
     * @return array<string,mixed>
     */
    public function update(string $id, array $body): array
    {
        return $this->http->request('PATCH', '/v1/permissions/' . rawurlencode($id), body: $body);
    }

    /** @return array<string,mixed> */
    public function delete(string $id): array
    {
        return $this->http->request('DELETE', '/v1/permissions/' . rawurlencode($id));
    }
}
