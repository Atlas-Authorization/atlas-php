<?php

declare(strict_types=1);

namespace Atlas\Resources;

/** `/v1/fga/stores/:storeId/authorization-models` — immutable, versioned FGA models. */
final class FgaModels extends Resource
{
    /** @return array<string,mixed> a ListPage of models */
    public function list(string $storeId): array
    {
        return $this->http->request('GET', '/v1/fga/stores/' . rawurlencode($storeId) . '/authorization-models');
    }

    /**
     * @param array{type_definitions:list<mixed>,schema_version?:string,conditions?:array<string,mixed>} $body
     *
     * @return array<string,mixed>
     */
    public function create(string $storeId, array $body, ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'POST',
            '/v1/fga/stores/' . rawurlencode($storeId) . '/authorization-models',
            body: $body,
            idempotencyKey: $idempotencyKey,
        );
    }

    /** @return array<string,mixed> */
    public function get(string $storeId, string $modelId): array
    {
        return $this->http->request(
            'GET',
            '/v1/fga/stores/' . rawurlencode($storeId) . '/authorization-models/' . rawurlencode($modelId),
        );
    }
}
