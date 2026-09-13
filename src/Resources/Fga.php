<?php

declare(strict_types=1);

namespace Atlas\Resources;

use Atlas\Http;

/**
 * `/v1/fga` — fine-grained relationship-based authorization (Zanzibar/OpenFGA).
 * A tenant owns stores; each store holds versioned authorization models and the
 * relationship tuples the engine resolves.
 */
final class Fga extends Resource
{
    public readonly FgaStores $stores;
    public readonly FgaModels $models;

    public function __construct(Http $http)
    {
        parent::__construct($http);
        $this->stores = new FgaStores($http);
        $this->models = new FgaModels($http);
    }

    /**
     * Write and/or delete tuples in one atomic call.
     *
     * @param array{writes?:mixed,deletes?:mixed} $body
     *
     * @return array<string,mixed>
     */
    public function write(string $storeId, array $body, ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'POST',
            '/v1/fga/stores/' . rawurlencode($storeId) . '/write',
            body: $body,
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * Query stored tuples by any of user / relation / object.
     *
     * @param array{user?:string,relation?:string,object?:string} $body
     *
     * @return array<string,mixed>
     */
    public function read(string $storeId, array $body): array
    {
        return $this->http->request('POST', '/v1/fga/stores/' . rawurlencode($storeId) . '/read', body: $body);
    }

    /**
     * Resolve a single access question against the model.
     *
     * @param array{user:string,relation:string,object:string,authorization_model_id?:string,contextual_tuples?:mixed} $body
     *
     * @return array<string,mixed>
     */
    public function check(string $storeId, array $body): array
    {
        return $this->http->request('POST', '/v1/fga/stores/' . rawurlencode($storeId) . '/check', body: $body);
    }

    /**
     * Resolve MANY access questions in one round trip.
     *
     * @param array{checks:list<array<string,mixed>>,authorization_model_id?:string} $body
     *
     * @return array<string,mixed>
     */
    public function batchCheck(string $storeId, array $body): array
    {
        return $this->http->request('POST', '/v1/fga/stores/' . rawurlencode($storeId) . '/batch-check', body: $body);
    }

    /**
     * List the objects of a type a user has a relation to.
     *
     * @param array{user:string,relation:string,type:string,authorization_model_id?:string,contextual_tuples?:mixed} $body
     *
     * @return array<string,mixed>
     */
    public function listObjects(string $storeId, array $body): array
    {
        return $this->http->request('POST', '/v1/fga/stores/' . rawurlencode($storeId) . '/list-objects', body: $body);
    }

    /**
     * Expand the full userset tree for an object#relation.
     *
     * @param array{object:string,relation:string,authorization_model_id?:string} $body
     *
     * @return array<string,mixed>
     */
    public function expand(string $storeId, array $body): array
    {
        return $this->http->request('POST', '/v1/fga/stores/' . rawurlencode($storeId) . '/expand', body: $body);
    }

    /**
     * Bind a default store so an app that uses one store can call
     * `$atlas->fga->store($id)->check([...])` without threading the store id
     * through every call.
     */
    public function store(string $storeId): FgaStoreScope
    {
        return new FgaStoreScope($this, $storeId);
    }
}
