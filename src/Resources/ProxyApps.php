<?php

declare(strict_types=1);

namespace Atlas\Resources;

/**
 * `/v1/proxy_apps` - 5 operations.
 */
final class ProxyApps extends Resource
{
    /**
     * `GET /v1/proxy_apps` - List the identity-aware proxy apps — hostnames protected by the forward-auth
     * endpoint, with their path rules.
     *
     * @param array{limit?:int,starting_after?:string} $params
     *
     * @return array<string,mixed>
     */
    public function list(array $params = []): array
    {
        return $this->http->request('GET', '/v1/proxy_apps', query: $params);
    }

    /**
     * `POST /v1/proxy_apps` - Create a proxy app: a hostname, optional upstream URL, ordered path/method
     * rules requiring a role, permission or entitlement, public paths, and header/token injection. The
     * hostname is unique across the deployment.
     *
     * @param array{name:string,hostname:string,upstream_url:string,rules?:list<array<string,mixed>>,public_paths?:list<string>,inject_headers?:bool,inject_token?:bool,allow_agent_tokens?:bool,enabled?:bool} $body
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>
     */
    public function create(array $body = [], array $params = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'POST',
            '/v1/proxy_apps',
            body: $body,
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * `GET /v1/proxy_apps/:id` - Read one identity-aware proxy app.
     *
     * @return array<string,mixed>
     */
    public function get(string $id): array
    {
        return $this->http->request('GET', '/v1/proxy_apps/' . rawurlencode($id));
    }

    /**
     * `PATCH /v1/proxy_apps/:id` - Update a proxy app. Omitted fields keep their value.
     *
     * @param array{name?:string,hostname?:string,upstream_url?:string,rules?:list<array<string,mixed>>,public_paths?:list<string>,inject_headers?:bool,inject_token?:bool,allow_agent_tokens?:bool,enabled?:bool} $body
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>
     */
    public function update(string $id, array $body = [], array $params = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'PATCH',
            '/v1/proxy_apps/' . rawurlencode($id),
            body: $body,
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * `DELETE /v1/proxy_apps/:id` - Delete a proxy app. Its hostname stops resolving at the forward-auth
     * endpoint.
     *
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>|null
     */
    public function delete(string $id, array $params = [], ?string $idempotencyKey = null): ?array
    {
        return $this->http->request(
            'DELETE',
            '/v1/proxy_apps/' . rawurlencode($id),
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }
}
