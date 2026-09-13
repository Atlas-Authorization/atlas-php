<?php

declare(strict_types=1);

namespace Atlas\Resources;

/** `/v1/fga/stores` — FGA stores. */
final class FgaStores extends Resource
{
    /** @return array<string,mixed> a ListPage of stores */
    public function list(): array
    {
        return $this->http->request('GET', '/v1/fga/stores');
    }

    /**
     * @param array{name:string} $body
     *
     * @return array<string,mixed>
     */
    public function create(array $body, ?string $idempotencyKey = null): array
    {
        return $this->http->request('POST', '/v1/fga/stores', body: $body, idempotencyKey: $idempotencyKey);
    }

    /** @return array<string,mixed> */
    public function get(string $id): array
    {
        return $this->http->request('GET', '/v1/fga/stores/' . rawurlencode($id));
    }

    /** @return array<string,mixed> */
    public function delete(string $id): array
    {
        return $this->http->request('DELETE', '/v1/fga/stores/' . rawurlencode($id));
    }
}
