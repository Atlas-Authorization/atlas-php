<?php

declare(strict_types=1);

namespace Atlas\Resources;

/**
 * `/v1/openfga` — openFGA-compatible relationship-based authorization (stores, models, tuples, checks).
 */
final class OpenFga extends Resource
{
    /**
     * `GET /v1/openfga/stores` — the instance's OpenFGA stores.
     *
     * @param array<string,mixed> $params query parameters
     *
     * @return array<string,mixed>
     */
    public function listStores(array $params = []): array
    {
        return $this->http->request('GET', '/v1/openfga/stores', query: $params);
    }

    /**
     * `POST /v1/openfga/stores` — create an OpenFGA store.
     *
     * @return array<string,mixed>
     */
    public function createStore(string $name, ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'POST',
            '/v1/openfga/stores',
            body: ['name' => $name],
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * `GET /v1/openfga/stores/:store` — one OpenFGA store.
     *
     * @return array<string,mixed>
     */
    public function getStore(string $storeId): array
    {
        return $this->http->request('GET', '/v1/openfga/stores/' . rawurlencode($storeId));
    }

    /**
     * `POST /v1/openfga/stores/:store/authorization-models` — write a new authorization model
     * (type_definitions + schema_version, or dsl).
     *
     * @param array<string,mixed> $body
     *
     * @return array<string,mixed>
     */
    public function writeAuthorizationModel(string $storeId, array $body): array
    {
        return $this->http->request(
            'POST',
            '/v1/openfga/stores/' . rawurlencode($storeId) . '/authorization-models',
            body: $body,
        );
    }

    /**
     * `GET /v1/openfga/stores/:store/authorization-models` — the authorization models in a store.
     *
     * @param array<string,mixed> $params query parameters
     *
     * @return array<string,mixed>
     */
    public function listAuthorizationModels(string $storeId, array $params = []): array
    {
        return $this->http->request(
            'GET',
            '/v1/openfga/stores/' . rawurlencode($storeId) . '/authorization-models',
            query: $params,
        );
    }

    /**
     * `GET /v1/openfga/stores/:store/authorization-models/:mid` — one authorization model.
     *
     * @return array<string,mixed>
     */
    public function getAuthorizationModel(string $storeId, string $modelId): array
    {
        return $this->http->request(
            'GET',
            '/v1/openfga/stores/' . rawurlencode($storeId) . '/authorization-models/' . rawurlencode($modelId),
        );
    }

    /**
     * `POST /v1/openfga/stores/:store/check` — check whether a user has a relation on an object.
     *
     * @param array<string,mixed> $body
     *
     * @return array<string,mixed>
     */
    public function check(string $storeId, array $body): array
    {
        return $this->http->request('POST', '/v1/openfga/stores/' . rawurlencode($storeId) . '/check', body: $body);
    }

    /**
     * `POST /v1/openfga/stores/:store/batch-check` — check up to 100 tuples in one request, keyed by
     * correlation id.
     *
     * @param array<string,mixed> $body
     *
     * @return array<string,mixed>
     */
    public function batchCheck(string $storeId, array $body): array
    {
        return $this->http->request(
            'POST',
            '/v1/openfga/stores/' . rawurlencode($storeId) . '/batch-check',
            body: $body,
        );
    }

    /**
     * `POST /v1/openfga/stores/:store/expand` — expand the userset tree for an object and relation.
     *
     * @param array<string,mixed> $body
     *
     * @return array<string,mixed>
     */
    public function expand(string $storeId, array $body): array
    {
        return $this->http->request('POST', '/v1/openfga/stores/' . rawurlencode($storeId) . '/expand', body: $body);
    }

    /**
     * `POST /v1/openfga/stores/:store/list-objects` — list the objects of a type a user has a relation on.
     *
     * @param array<string,mixed> $body
     *
     * @return array<string,mixed>
     */
    public function listObjects(string $storeId, array $body): array
    {
        return $this->http->request(
            'POST',
            '/v1/openfga/stores/' . rawurlencode($storeId) . '/list-objects',
            body: $body,
        );
    }

    /**
     * `POST /v1/openfga/stores/:store/read` — read relationship tuples, optionally filtered by a tuple
     * key.
     *
     * @param array<string,mixed> $body
     *
     * @return array<string,mixed>
     */
    public function read(string $storeId, array $body = []): array
    {
        return $this->http->request('POST', '/v1/openfga/stores/' . rawurlencode($storeId) . '/read', body: $body);
    }

    /**
     * `POST /v1/openfga/stores/:store/write` — write and/or delete relationship tuples.
     *
     * @param array<string,mixed> $body
     *
     * @return array<string,mixed>
     */
    public function write(string $storeId, array $body): array
    {
        return $this->http->request('POST', '/v1/openfga/stores/' . rawurlencode($storeId) . '/write', body: $body);
    }
}
