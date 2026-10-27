<?php

declare(strict_types=1);

namespace Atlas\Resources;

use Atlas\Http;

/**
 * `/v1` - 8 operations.
 */
final class Resources extends Resource
{
    public readonly ResourcesTypes $types;
    public readonly ResourcesAssignments $assignments;

    public function __construct(Http $http)
    {
        parent::__construct($http);
        $this->types = new ResourcesTypes($http);
        $this->assignments = new ResourcesAssignments($http);
    }

    /**
     * `GET /v1/resources` - List resources, optionally filtered by `type` or `parent_id`.
     *
     * @param array{type?:string,parent_id?:string,limit?:int,starting_after?:string} $params
     *
     * @return array<string,mixed>
     */
    public function list(array $params = []): array
    {
        return $this->http->request('GET', '/v1/resources', query: $params);
    }

    /**
     * `PUT /v1/resources` - Create or update one resource by `type` + `external_id` (your own id for it).
     * `parent` is `{id}` or `{type, external_id}` of an existing resource, or null; it must match the
     * type’s `parent_type`.
     *
     * @param array{type?:string,external_id?:string,name?:string|null,parent?:array<string,mixed>|null,metadata?:array<string,mixed>} $body
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>
     */
    public function upsert(array $body = [], array $params = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'PUT',
            '/v1/resources',
            body: $body,
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * `POST /v1/resources/sync` - Discovery sync: upsert up to 500 resources in order (list parents before
     * children). Idempotent; call it whenever your app creates or moves resources.
     *
     * @param array{resources?:list<array<string,mixed>>} $body
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>
     */
    public function sync(array $body = [], array $params = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'POST',
            '/v1/resources/sync',
            body: $body,
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * `GET /v1/resources/:id` - Read one resource, with its type and parent.
     *
     * @return array<string,mixed>
     */
    public function get(string $id): array
    {
        return $this->http->request('GET', '/v1/resources/' . rawurlencode($id));
    }

    /**
     * `DELETE /v1/resources/:id` - Delete a resource and its role assignments. Its children are detached
     * to the root.
     *
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>|null
     */
    public function delete(string $id, array $params = [], ?string $idempotencyKey = null): ?array
    {
        return $this->http->request(
            'DELETE',
            '/v1/resources/' . rawurlencode($id),
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * `POST /v1/resource_checks` - Does `subject` hold `permission` on `resource`? A user subject also
     * holds the roles of the organizations they belong to, plus any `groups` ids you pass. The response
     * lists the grants that allow it.
     *
     * @param array{subject?:array<string,mixed>,permission?:string,resource?:array<string,mixed>,groups?:list<string>} $body
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>
     */
    public function check(array $body = [], array $params = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'POST',
            '/v1/resource_checks',
            body: $body,
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * `POST /v1/resource_checks/batch` - Up to 100 resource checks in one call.
     *
     * @param array{checks?:list<array<string,mixed>>} $body
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>
     */
    public function checkBatch(array $body = [], array $params = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'POST',
            '/v1/resource_checks/batch',
            body: $body,
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * `POST /v1/resource_checks/list` - List the resources of an optional `type` on which `subject` holds
     * `permission`, paged with `limit` and `starting_after` (the response carries `has_more` and
     * `next_cursor`).
     *
     * @param array{subject?:array<string,mixed>,permission?:string,type?:string,groups?:list<string>,limit?:int,starting_after?:string} $body
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>
     */
    public function accessible(array $body = [], array $params = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'POST',
            '/v1/resource_checks/list',
            body: $body,
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }
}
