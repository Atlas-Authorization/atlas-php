<?php

declare(strict_types=1);

namespace Atlas\Resources;

use Atlas\Http;

/**
 * `/v1/organizations` — orgs plus their memberships, invitations, domains and
 * directory-group role grants (the nested sub-resources below).
 */
final class Organizations extends Resource
{
    public readonly OrganizationMemberships $memberships;
    public readonly OrganizationInvitations $invitations;
    public readonly OrganizationDomains $domains;
    public readonly OrganizationGroupRoles $groupRoles;

    public function __construct(Http $http)
    {
        parent::__construct($http);
        $this->memberships = new OrganizationMemberships($http);
        $this->invitations = new OrganizationInvitations($http);
        $this->domains = new OrganizationDomains($http);
        $this->groupRoles = new OrganizationGroupRoles($http);
    }

    /**
     * @param array{limit?:int,starting_after?:string} $params
     *
     * @return array<string,mixed> a CursorPage of organizations
     */
    public function list(array $params = []): array
    {
        return $this->http->request('GET', '/v1/organizations', query: $params);
    }

    /** @return array<string,mixed> */
    public function get(string $id): array
    {
        return $this->http->request('GET', '/v1/organizations/' . rawurlencode($id));
    }

    /**
     * @param array{name:string,slug:string,created_by:string,max_allowed_memberships?:int} $body
     *
     * @return array<string,mixed>
     */
    public function create(array $body, ?string $idempotencyKey = null): array
    {
        return $this->http->request('POST', '/v1/organizations', body: $body, idempotencyKey: $idempotencyKey);
    }

    /**
     * @param array<string,mixed> $body
     *
     * @return array<string,mixed>
     */
    public function update(string $id, array $body): array
    {
        return $this->http->request('PATCH', '/v1/organizations/' . rawurlencode($id), body: $body);
    }

    /** @return array<string,mixed> a DeletedObject */
    public function delete(string $id): array
    {
        return $this->http->request('DELETE', '/v1/organizations/' . rawurlencode($id));
    }

    /**
     * `PUT /v1/organizations/:id/metadata` — replaces the named bags wholesale.
     *
     * @param array<string,mixed> $body
     *
     * @return array<string,mixed>
     */
    public function updateMetadata(string $id, array $body): array
    {
        return $this->http->request('PUT', '/v1/organizations/' . rawurlencode($id) . '/metadata', body: $body);
    }

    /**
     * `PATCH /v1/organizations/:id/policy` — the org security policy (camelCase fields).
     *
     * @param array<string,mixed> $body {requireMfa?, ssoRequired?, sessionIdleOverrideMs?}
     *
     * @return array<string,mixed>
     */
    public function updatePolicy(string $id, array $body): array
    {
        return $this->http->request('PATCH', '/v1/organizations/' . rawurlencode($id) . '/policy', body: $body);
    }

    /**
     * `PUT /v1/organizations/:id/parent` — set (or clear, with null) the parent org.
     *
     * @param array{parent_organization_id:?string} $body
     *
     * @return array<string,mixed>
     */
    public function setParent(string $id, array $body): array
    {
        return $this->http->request('PUT', '/v1/organizations/' . rawurlencode($id) . '/parent', body: $body);
    }

    /** `GET /v1/organizations/:id/hierarchy` — ancestor chain + direct children. @return array<string,mixed> */
    public function hierarchy(string $id): array
    {
        return $this->http->request('GET', '/v1/organizations/' . rawurlencode($id) . '/hierarchy');
    }

    /** `GET /v1/organizations/:id/entitlements` — the org's active feature set. @return array<string,mixed> */
    public function entitlements(string $id): array
    {
        return $this->http->request('GET', '/v1/organizations/' . rawurlencode($id) . '/entitlements');
    }
}
