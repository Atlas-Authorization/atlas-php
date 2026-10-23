<?php

declare(strict_types=1);

namespace Atlas\Resources;

/**
 * `/v1/organizations/:id/roles` — the roles an organization can assign: the
 * instance roles plus its own custom roles (`organization_id` set).
 */
final class OrganizationRoles extends Resource
{
    private static function base(string $orgId): string
    {
        return '/v1/organizations/' . rawurlencode($orgId) . '/roles';
    }

    /**
     * `$own` lists only the organization's own custom roles.
     *
     * @return array<string,mixed>
     */
    public function list(string $orgId, bool $own = false): array
    {
        return $this->http->request('GET', self::base($orgId), query: $own ? ['own' => 'true'] : null);
    }

    /**
     * @param array{key:string,name:string,description?:string|null,permissions?:list<string>} $body
     *
     * @return array<string,mixed>
     */
    public function create(string $orgId, array $body): array
    {
        return $this->http->request('POST', self::base($orgId), body: $body);
    }

    /**
     * @param array{name?:string,description?:string|null,permissions?:list<string>} $body
     *
     * @return array<string,mixed>
     */
    public function update(string $orgId, string $roleId, array $body): array
    {
        return $this->http->request('PATCH', self::base($orgId) . '/' . rawurlencode($roleId), body: $body);
    }

    /**
     * `$reassignTo` (a role key) moves members and pending invitations first;
     * without it a role in use is refused (409 ROLE_IN_USE).
     *
     * @return array<string,mixed>
     */
    public function delete(string $orgId, string $roleId, ?string $reassignTo = null): array
    {
        return $this->http->request(
            'DELETE',
            self::base($orgId) . '/' . rawurlencode($roleId),
            query: $reassignTo === null ? null : ['reassign_to' => $reassignTo],
        );
    }
}
