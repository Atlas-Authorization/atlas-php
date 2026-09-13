<?php

declare(strict_types=1);

namespace Atlas\Resources;

use Atlas\Http;

/** `/v1/oauth_clients` — first/third-party OAuth clients and their grants. */
final class OAuthClients extends Resource
{
    public readonly OAuthClientGrants $grants;

    public function __construct(Http $http)
    {
        parent::__construct($http);
        $this->grants = new OAuthClientGrants($http);
    }

    /** @return array<string,mixed> a ListPage of clients */
    public function list(): array
    {
        return $this->http->request('GET', '/v1/oauth_clients');
    }

    /** @return array<string,mixed> */
    public function get(string $id): array
    {
        return $this->http->request('GET', '/v1/oauth_clients/' . rawurlencode($id));
    }

    /**
     * Create a client; the secret is revealed exactly once, here.
     *
     * @param array{name:string,redirect_uris:list<string>,allowed_scopes?:list<string>,grant_types?:list<string>,token_endpoint_auth_method?:string,logo_url?:string,first_party?:bool} $body
     *
     * @return array<string,mixed>
     */
    public function create(array $body, ?string $idempotencyKey = null): array
    {
        return $this->http->request('POST', '/v1/oauth_clients', body: $body, idempotencyKey: $idempotencyKey);
    }

    /**
     * @param array<string,mixed> $body
     *
     * @return array<string,mixed>
     */
    public function update(string $id, array $body): array
    {
        return $this->http->request('PATCH', '/v1/oauth_clients/' . rawurlencode($id), body: $body);
    }

    /** @return array<string,mixed> the client with a freshly revealed secret */
    public function rotateSecret(string $id): array
    {
        return $this->http->request('POST', '/v1/oauth_clients/' . rawurlencode($id) . '/rotate_secret');
    }

    /** @return array<string,mixed> */
    public function delete(string $id): array
    {
        return $this->http->request('DELETE', '/v1/oauth_clients/' . rawurlencode($id));
    }
}
