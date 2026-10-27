<?php

declare(strict_types=1);

namespace Atlas\Resources;

/**
 * `/v1/resource_types` - 5 operations.
 */
final class ResourcesTypes extends Resource
{
    /**
     * `GET /v1/resource_types` - List the resource types (roles, permissions, parent type).
     *
     * @param array{limit?:int,starting_after?:string} $params
     *
     * @return array<string,mixed>
     */
    public function list(array $params = []): array
    {
        return $this->http->request('GET', '/v1/resource_types', query: $params);
    }

    /**
     * `POST /v1/resource_types` - Define a resource type: a `key`, its `roles` (each a set of permissions,
     * optionally `includes` other roles), an optional `parent_type` resources nest under, and `inherit`
     * (default true) controlling whether assignments on a parent flow down.
     *
     * @param array{key?:string,name?:string,description?:string|null,parent_type?:string|null,inherit?:bool,roles?:list<array<string,mixed>>} $body
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>
     */
    public function create(array $body = [], array $params = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'POST',
            '/v1/resource_types',
            body: $body,
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * `GET /v1/resource_types/:key` - Read one resource type.
     *
     * @return array<string,mixed>
     */
    public function get(string $key): array
    {
        return $this->http->request('GET', '/v1/resource_types/' . rawurlencode($key));
    }

    /**
     * `PATCH /v1/resource_types/:key` - Change a resource type’s name, roles, parent type or inheritance.
     *
     * @param array{name?:string,description?:string|null,parent_type?:string|null,inherit?:bool,roles?:list<array<string,mixed>>} $body
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>
     */
    public function update(string $key, array $body = [], array $params = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'PATCH',
            '/v1/resource_types/' . rawurlencode($key),
            body: $body,
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * `DELETE /v1/resource_types/:key` - Delete a resource type together with its resources and their role
     * assignments.
     *
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>|null
     */
    public function delete(string $key, array $params = [], ?string $idempotencyKey = null): ?array
    {
        return $this->http->request(
            'DELETE',
            '/v1/resource_types/' . rawurlencode($key),
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }
}
