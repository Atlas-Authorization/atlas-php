<?php

declare(strict_types=1);

namespace Atlas\Resources;

/**
 * `/v1/app_roles` — instance-wide user roles, not tied to any organization. A
 * user's app roles ride on the session token as `app_roles` / `app_permissions`;
 * check them offline with {@see \Atlas\Verify\VerifyResult::hasAppRole()} /
 * {@see \Atlas\Verify\VerifyResult::hasAppPermission()}.
 */
final class AppRoles extends Resource
{
    /** @return array<string,mixed> */
    public function list(): array
    {
        return $this->http->request('GET', '/v1/app_roles');
    }

    /**
     * @param array{key:string,name:string,description?:string|null,permissions?:list<string>} $body
     *
     * @return array<string,mixed>
     */
    public function create(array $body): array
    {
        return $this->http->request('POST', '/v1/app_roles', body: $body);
    }

    /**
     * By id or key.
     *
     * @return array<string,mixed>
     */
    public function get(string $id): array
    {
        return $this->http->request('GET', '/v1/app_roles/' . rawurlencode($id));
    }

    /**
     * @param array{name?:string,description?:string|null,permissions?:list<string>} $body
     *
     * @return array<string,mixed>
     */
    public function update(string $id, array $body): array
    {
        return $this->http->request('PATCH', '/v1/app_roles/' . rawurlencode($id), body: $body);
    }

    /** @return array<string,mixed> */
    public function delete(string $id): array
    {
        return $this->http->request('DELETE', '/v1/app_roles/' . rawurlencode($id));
    }

    /**
     * The users holding a role, oldest assignment first.
     *
     * @param array{limit?:int,offset?:int} $params
     *
     * @return array<string,mixed>
     */
    public function listUsers(string $id, array $params = []): array
    {
        return $this->http->request('GET', '/v1/app_roles/' . rawurlencode($id) . '/users', query: $params === [] ? null : $params);
    }

    /**
     * A user's app roles and effective permissions.
     *
     * @return array<string,mixed>
     */
    public function getForUser(string $userId): array
    {
        return $this->http->request('GET', '/v1/users/' . rawurlencode($userId) . '/app_roles');
    }

    /**
     * `roles` replaces the set; `add` / `remove` change it (keys or ids).
     *
     * @param array{roles?:list<string>,add?:list<string>,remove?:list<string>} $body
     *
     * @return array<string,mixed>
     */
    public function setForUser(string $userId, array $body): array
    {
        return $this->http->request('POST', '/v1/users/' . rawurlencode($userId) . '/app_roles', body: $body);
    }

    /** @return array<string,mixed> */
    public function removeFromUser(string $userId, string $role): array
    {
        return $this->http->request('DELETE', '/v1/users/' . rawurlencode($userId) . '/app_roles/' . rawurlencode($role));
    }
}
