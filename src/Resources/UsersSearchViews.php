<?php

declare(strict_types=1);

namespace Atlas\Resources;

/**
 * `/v1/users/search/views` - 4 operations.
 */
final class UsersSearchViews extends Resource
{
    /**
     * `GET /v1/users/search/views` - The saved user-search views of the instance, by name.
     *
     * @param array{limit?:int,starting_after?:string} $params
     *
     * @return array<string,mixed>
     */
    public function list(array $params = []): array
    {
        return $this->http->request('GET', '/v1/users/search/views', query: $params);
    }

    /**
     * `POST /v1/users/search/views` - Save a named filter (and sort) to re-run. A filter that could never
     * run is refused; a duplicate name is a 400. → 201.
     *
     * @param array{name:string,filter?:array{and?:list<mixed>,or?:list<mixed>,not?:array<string,mixed>,field?:string,op?:string,value?:string|int|float|bool|list<mixed>},order_by?:string|null} $body
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>
     */
    public function create(array $body, array $params = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'POST',
            '/v1/users/search/views',
            body: $body,
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * `PATCH /v1/users/search/views/:id` - Rename a saved view or change its filter or sort.
     *
     * @param array{name?:string,filter?:array{and?:list<mixed>,or?:list<mixed>,not?:array<string,mixed>,field?:string,op?:string,value?:string|int|float|bool|list<mixed>},order_by?:string|null} $body
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>
     */
    public function update(string $id, array $body, array $params = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'PATCH',
            '/v1/users/search/views/' . rawurlencode($id),
            body: $body,
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * `DELETE /v1/users/search/views/:id` - Delete a saved user-search view.
     *
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>
     */
    public function delete(string $id, array $params = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'DELETE',
            '/v1/users/search/views/' . rawurlencode($id),
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }
}
