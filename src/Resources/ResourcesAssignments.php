<?php

declare(strict_types=1);

namespace Atlas\Resources;

/**
 * `/v1/resource_role_assignments` - 3 operations.
 */
final class ResourcesAssignments extends Resource
{
    /**
     * `GET /v1/resource_role_assignments` - List role assignments, filtered by `resource_id`,
     * `subject_type` or `subject_id`.
     *
     * @param array{resource_id?:string,subject_type?:'user'|'group'|'organization',subject_id?:string,limit?:int,starting_after?:string} $params
     *
     * @return array<string,mixed>
     */
    public function list(array $params = []): array
    {
        return $this->http->request('GET', '/v1/resource_role_assignments', query: $params);
    }

    /**
     * `POST /v1/resource_role_assignments` - Assign a `role` on a `resource` to a `subject` (`{type:
     * user|group|org, id}`). `inherit` (default true) also applies it to the resource’s descendants.
     * Re-assigning the same role updates `inherit`.
     *
     * @param array{resource?:array<string,mixed>,subject?:array<string,mixed>,role?:string,inherit?:bool} $body
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>
     */
    public function assign(array $body = [], array $params = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'POST',
            '/v1/resource_role_assignments',
            body: $body,
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * `DELETE /v1/resource_role_assignments/:id` - Revoke a role assignment.
     *
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>|null
     */
    public function revoke(string $id, array $params = [], ?string $idempotencyKey = null): ?array
    {
        return $this->http->request(
            'DELETE',
            '/v1/resource_role_assignments/' . rawurlencode($id),
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }
}
