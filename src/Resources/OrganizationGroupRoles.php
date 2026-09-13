<?php

declare(strict_types=1);

namespace Atlas\Resources;

/**
 * `/v1/organizations/:id/groups/:groupId/roles/:roleId` — directory-group → role
 * grants that augment members' permissions.
 */
final class OrganizationGroupRoles extends Resource
{
    /** @return array<string,mixed> */
    public function grant(string $orgId, string $groupId, string $roleId): array
    {
        return $this->http->request(
            'PUT',
            '/v1/organizations/' . rawurlencode($orgId)
                . '/groups/' . rawurlencode($groupId)
                . '/roles/' . rawurlencode($roleId),
        );
    }

    /** @return array<string,mixed> */
    public function revoke(string $orgId, string $groupId, string $roleId): array
    {
        return $this->http->request(
            'DELETE',
            '/v1/organizations/' . rawurlencode($orgId)
                . '/groups/' . rawurlencode($groupId)
                . '/roles/' . rawurlencode($roleId),
        );
    }
}
