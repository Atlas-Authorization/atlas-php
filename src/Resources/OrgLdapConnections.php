<?php

declare(strict_types=1);

namespace Atlas\Resources;

/**
 * `/v1/org_ldap_connections` — organization-owned LDAP / Active Directory connections.
 */
final class OrgLdapConnections extends Resource
{
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
}
