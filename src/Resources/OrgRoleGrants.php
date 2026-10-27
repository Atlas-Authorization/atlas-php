<?php

declare(strict_types=1);

namespace Atlas\Resources;

/**
 * `/v1/org_role_grants` - 9 operations.
 */
final class OrgRoleGrants extends Resource
{
    /**
     * `GET /v1/org_role_grants` - List cross-organization role grants, filtered by grantor_org_id,
     * grantee_org_id or status=active.
     *
     * @param array{grantor_org_id?:string,grantee_org_id?:string,status?:'active',limit?:int,starting_after?:string} $params
     *
     * @return array<string,mixed>
     */
    public function list(array $params = []): array
    {
        return $this->http->request('GET', '/v1/org_role_grants', query: $params);
    }

    /**
     * `POST /v1/org_role_grants` - Let a grantee organization hand out a bounded set of the grantor
     * organization’s roles (role_keys) to its own members. One active grant per pair.
     *
     * @param array{grantor_org_id?:string,grantee_org_id?:string,role_keys?:list<string>} $body
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>
     */
    public function create(array $body = [], array $params = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'POST',
            '/v1/org_role_grants',
            body: $body,
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * `GET /v1/org_role_grants/:id` - Fetch one cross-organization role grant.
     *
     * @return array<string,mixed>
     */
    public function get(string $id): array
    {
        return $this->http->request('GET', '/v1/org_role_grants/' . rawurlencode($id));
    }

    /**
     * `PATCH /v1/org_role_grants/:id` - Replace the granted role_keys. Delegations of roles no longer
     * granted are removed.
     *
     * @param array{role_keys?:list<string>} $body
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>
     */
    public function update(string $id, array $body = [], array $params = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'PATCH',
            '/v1/org_role_grants/' . rawurlencode($id),
            body: $body,
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * `DELETE /v1/org_role_grants/:id` - Revoke a grant. Every delegation under it stops taking effect
     * immediately.
     *
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>
     */
    public function revoke(string $id, array $params = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'DELETE',
            '/v1/org_role_grants/' . rawurlencode($id),
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * `GET /v1/org_role_grants/:id/delegations` - List the members holding roles under a grant.
     *
     * @param array{limit?:int,starting_after?:string} $params
     *
     * @return array<string,mixed>
     */
    public function listDelegations(string $id, array $params = []): array
    {
        return $this->http->request('GET', '/v1/org_role_grants/' . rawurlencode($id) . '/delegations', query: $params);
    }

    /**
     * `POST /v1/org_role_grants/:id/delegations` - Delegate one granted role_key to a member (user_id) of
     * the grantee organization.
     *
     * @param array{user_id?:string,role_key?:string} $body
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>
     */
    public function delegate(string $id, array $body = [], array $params = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'POST',
            '/v1/org_role_grants/' . rawurlencode($id) . '/delegations',
            body: $body,
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * `DELETE /v1/org_role_grants/:id/delegations/:delegationId` - Remove a delegation of a granted role.
     *
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>|null
     */
    public function undelegate(string $id, string $delegationId, array $params = [], ?string $idempotencyKey = null): ?array
    {
        return $this->http->request(
            'DELETE',
            '/v1/org_role_grants/' . rawurlencode($id) . '/delegations/' . rawurlencode($delegationId),
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * `GET /v1/org_role_grants/effective` - The roles and permissions a user (user_id) holds in a grantor
     * organization (organization_id, optional) through active delegations.
     *
     * @param array{user_id?:string,organization_id?:string,limit?:int,starting_after?:string} $params
     *
     * @return array<string,mixed>
     */
    public function effective(array $params = []): array
    {
        return $this->http->request('GET', '/v1/org_role_grants/effective', query: $params);
    }
}
