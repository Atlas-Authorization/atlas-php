<?php

declare(strict_types=1);

namespace Atlas\Resources;

/**
 * `/v1/ldap_server_clients` - 6 operations.
 */
final class LdapServerClients extends Resource
{
    /**
     * `GET /v1/ldap_server_clients` - List the LDAP interface service accounts with their base DN, bind DN
     * and allowed source networks. Secrets are never returned.
     *
     * @param array{limit?:int,starting_after?:string} $params
     *
     * @return array<string,mixed>
     */
    public function list(array $params = []): array
    {
        return $this->http->request('GET', '/v1/ldap_server_clients', query: $params);
    }

    /**
     * `POST /v1/ldap_server_clients` - Create a service account for the LDAPS interface: base_dn
     * (dc=/o=/ou= parts, unique across Atlas), bind_cn, allowed_cidrs (empty = nobody may connect),
     * allow_user_bind and size_limit. The secret is returned once as secret.
     *
     * @param array{name:string,base_dn:string,bind_cn:string,allowed_cidrs?:list<string>,allow_user_bind?:bool,size_limit?:int,status?:'active'|'disabled'} $body
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>
     */
    public function create(array $body = [], array $params = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'POST',
            '/v1/ldap_server_clients',
            body: $body,
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * `GET /v1/ldap_server_clients/:id` - Read one LDAP interface service account.
     *
     * @return array<string,mixed>
     */
    public function get(string $id): array
    {
        return $this->http->request('GET', '/v1/ldap_server_clients/' . rawurlencode($id));
    }

    /**
     * `PATCH /v1/ldap_server_clients/:id` - Change the name, allowed networks, user-bind switch, size
     * limit or status (active or disabled).
     *
     * @param array{name?:string,base_dn?:string,bind_cn?:string,allowed_cidrs?:list<string>,allow_user_bind?:bool,size_limit?:int,status?:'active'|'disabled'} $body
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>
     */
    public function update(string $id, array $body = [], array $params = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'PATCH',
            '/v1/ldap_server_clients/' . rawurlencode($id),
            body: $body,
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * `DELETE /v1/ldap_server_clients/:id` - Delete a service account; its connections can no longer bind.
     *
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>|null
     */
    public function delete(string $id, array $params = [], ?string $idempotencyKey = null): ?array
    {
        return $this->http->request(
            'DELETE',
            '/v1/ldap_server_clients/' . rawurlencode($id),
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * `POST /v1/ldap_server_clients/:id/rotate_secret` - Issue a new service-account secret (returned
     * once); the old one stops working immediately.
     *
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>
     */
    public function rotateSecret(string $id, array $params = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'POST',
            '/v1/ldap_server_clients/' . rawurlencode($id) . '/rotate_secret',
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }
}
