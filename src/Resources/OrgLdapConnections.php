<?php

declare(strict_types=1);

namespace Atlas\Resources;

use Atlas\Http;

/**
 * `/v1/org_ldap_connections` — organization-owned LDAP / Active Directory connections.
 */
final class OrgLdapConnections extends Resource
{
    public readonly OrgLdapConnectionsSyncRuns $syncRuns;

    public function __construct(Http $http)
    {
        parent::__construct($http);
        $this->syncRuns = new OrgLdapConnectionsSyncRuns($http);
    }

    /**
     * `GET /v1/org_ldap_connections` — organization-owned LDAP / Active Directory connections (never the
     * bind password). Filter with organization_id.
     *
     * @param array<string,mixed> $params query parameters
     *
     * @return array<string,mixed>
     */
    public function list(array $params = []): array
    {
        return $this->http->request('GET', '/v1/org_ldap_connections', query: $params);
    }

    /**
     * `POST /v1/org_ldap_connections` — create an organization's LDAP connection (one per organization).
     *
     * @param array<string,mixed> $body
     *
     * @return array<string,mixed>
     */
    public function create(array $body, ?string $idempotencyKey = null): array
    {
        return $this->http->request('POST', '/v1/org_ldap_connections', body: $body, idempotencyKey: $idempotencyKey);
    }

    /**
     * `GET /v1/org_ldap_connections/:id` — one LDAP connection.
     *
     * @return array<string,mixed>
     */
    public function get(string $id): array
    {
        return $this->http->request('GET', '/v1/org_ldap_connections/' . rawurlencode($id));
    }

    /**
     * `PATCH /v1/org_ldap_connections/:id` — update an LDAP connection.
     *
     * @param array<string,mixed> $body
     *
     * @return array<string,mixed>
     */
    public function update(string $id, array $body, ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'PATCH',
            '/v1/org_ldap_connections/' . rawurlencode($id),
            body: $body,
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * `DELETE /v1/org_ldap_connections/:id` — delete an LDAP connection.
     *
     * @return array<string,mixed>
     */
    public function delete(string $id, ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'DELETE',
            '/v1/org_ldap_connections/' . rawurlencode($id),
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * `POST /v1/org_ldap_connections/:id/test` — test an LDAP connection with a service-account bind.
     *
     * @param array<string,mixed> $body
     *
     * @return array<string,mixed>
     */
    public function test(string $id, array $body = []): array
    {
        return $this->http->request('POST', '/v1/org_ldap_connections/' . rawurlencode($id) . '/test', body: $body);
    }

    /**
     * `GET /v1/org_ldap_connections/:id/sync` - Read the directory-sync settings of an organization LDAP /
     * Active Directory connection (schedule, filters, group-to-role mapping, disable-on-removal).
     *
     * @return array<string,mixed>
     */
    public function getSync(string $id): array
    {
        return $this->http->request('GET', '/v1/org_ldap_connections/' . rawurlencode($id) . '/sync');
    }

    /**
     * `PUT /v1/org_ldap_connections/:id/sync` - Set the directory-sync settings. Fields you omit keep
     * their value. Enabling requires an active connection and schedules the first run immediately;
     * disabling clears the schedule. Role mappings are matched in order, by group DN.
     *
     * @param array{enabled?:bool,intervalMinutes?:int|float,pageSize?:int|float,idAttribute?:string,userFilter?:string|null,groupBaseDN?:string|null,groupFilter?:string,groupMemberAttribute?:string,groupNameAttribute?:string,syncGroups?:bool,roleMappings?:list<array<string,mixed>>,disableOnRemoval?:bool,deltaAttribute?:string} $body
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>|null
     */
    public function setSync(string $id, array $body = [], array $params = [], ?string $idempotencyKey = null): ?array
    {
        return $this->http->request(
            'PUT',
            '/v1/org_ldap_connections/' . rawurlencode($id) . '/sync',
            body: $body,
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * `GET /v1/org_ldap_connections/:id/relay` - Which relay agent an organization LDAP connection reaches
     * its directory through, if any.
     *
     * @return array<string,mixed>
     */
    public function getRelay(string $id): array
    {
        return $this->http->request('GET', '/v1/org_ldap_connections/' . rawurlencode($id) . '/relay');
    }

    /**
     * `PUT /v1/org_ldap_connections/:id/relay` - Route an LDAP connection through a relay agent
     * (agent_id), or back to a direct connection (null). The agent must be active and allow the LDAP
     * server.
     *
     * @param array{agent_id?:string|null} $body
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>|null
     */
    public function setRelay(string $id, array $body = [], array $params = [], ?string $idempotencyKey = null): ?array
    {
        return $this->http->request(
            'PUT',
            '/v1/org_ldap_connections/' . rawurlencode($id) . '/relay',
            body: $body,
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }
}
