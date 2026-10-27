<?php

declare(strict_types=1);

namespace Atlas\Resources;

/**
 * `/v1/connections/oauth_clients` - 4 operations.
 */
final class ConnectionsOauthClients extends Resource
{
    /**
     * `PUT /v1/connections/oauth_clients` - Set an owner's own OAuth client (their registered app) for a
     * provider and environment. It overrides the instance client for that owner's connections to connect
     * and to refresh. Write-only secret, sealed in the vault. Creates the client or replaces it.
     *
     * @param array{owner?:array{type:'organization'|'user',id:string},provider:string,environment?:'production'|'sandbox',client_id:string,client_secret?:string,scopes?:list<string>,host_params?:array<string,mixed>,enabled?:bool} $body
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>
     */
    public function set(array $body, array $params = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'PUT',
            '/v1/connections/oauth_clients',
            body: $body,
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * `GET /v1/connections/oauth_clients` - List owner OAuth clients. The secret is never returned.
     *
     * @param array{owner_type?:'organization'|'user',owner_id?:string,provider?:string,limit?:int,starting_after?:string} $params
     *
     * @return array<string,mixed>
     */
    public function list(array $params = []): array
    {
        return $this->http->request('GET', '/v1/connections/oauth_clients', query: $params);
    }

    /**
     * `GET /v1/connections/oauth_clients/:id` - Read one owner OAuth client.
     *
     * @return array<string,mixed>
     */
    public function get(string $id): array
    {
        return $this->http->request('GET', '/v1/connections/oauth_clients/' . rawurlencode($id));
    }

    /**
     * `DELETE /v1/connections/oauth_clients/:id` - Delete an owner OAuth client and its sealed secret. 409
     * while connections still hold tokens it issued (they could no longer refresh).
     *
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>|null
     */
    public function delete(string $id, array $params = [], ?string $idempotencyKey = null): ?array
    {
        return $this->http->request(
            'DELETE',
            '/v1/connections/oauth_clients/' . rawurlencode($id),
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }
}
