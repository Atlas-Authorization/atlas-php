<?php

declare(strict_types=1);

namespace Atlas\Resources;

/** `/v1/roles` — instance roles and their permission sets. */
final class Roles extends Resource
{
    /** @return array<string,mixed> a ListPage of roles */
    public function list(): array
    {
        return $this->http->request('GET', '/v1/roles');
    }

    /**
     * @param array{key:string,name:string,description?:string,permissions?:list<string>} $body
     *
     * @return array<string,mixed>
     */
    public function create(array $body, ?string $idempotencyKey = null): array
    {
        return $this->http->request('POST', '/v1/roles', body: $body, idempotencyKey: $idempotencyKey);
    }

    /**
     * @param array{name?:string,description?:string,key?:string} $body
     *
     * @return array<string,mixed>
     */
    public function update(string $id, array $body): array
    {
        return $this->http->request('PATCH', '/v1/roles/' . rawurlencode($id), body: $body);
    }

    /**
     * Replace a role's permission set wholesale.
     *
     * @param list<string> $permissions
     *
     * @return array<string,mixed>
     */
    public function setPermissions(string $id, array $permissions): array
    {
        return $this->http->request(
            'PUT',
            '/v1/roles/' . rawurlencode($id) . '/permissions',
            body: ['permissions' => $permissions],
        );
    }

    /**
     * Delete a role. Pass `$reassignTo` to move every member onto another role
     * first (atomic) so an in-use role can be retired.
     *
     * @return array<string,mixed>
     */
    public function delete(string $id, ?string $reassignTo = null): array
    {
        return $this->http->request(
            'DELETE',
            '/v1/roles/' . rawurlencode($id),
            query: $reassignTo !== null ? ['reassign_to' => $reassignTo] : null,
        );
    }
}
