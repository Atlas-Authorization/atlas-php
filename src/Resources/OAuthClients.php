<?php

declare(strict_types=1);

namespace Atlas\Resources;

use Atlas\Http;

/**
 * `/v1/oauth_clients` — first/third-party OAuth clients and their grants.
 *
 * `skip_logout_confirmation` (first-party clients only; the API answers 422
 * `VALIDATION_FAILED` with param `skip_logout_confirmation` on a third-party
 * client): when true, RP-initiated logout (`/oauth2/logout`) with this
 * client_id and an exactly registered post_logout_redirect_uri ends the session
 * and redirects straight back with `state`, without the confirmation page.
 * Client arrays carry it as a bool (false by default).
 */
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
     * @param array{name:string,redirect_uris:list<string>,allowed_scopes?:list<string>,grant_types?:list<string>,token_endpoint_auth_method?:string,logo_url?:string,first_party?:bool,skip_logout_confirmation?:bool} $body
     *
     * @return array<string,mixed>
     */
    public function create(array $body, ?string $idempotencyKey = null): array
    {
        return $this->http->request('POST', '/v1/oauth_clients', body: $body, idempotencyKey: $idempotencyKey);
    }

    /**
     * Partially update a client. Send `skip_logout_confirmation` (bool) to turn the logout
     * confirmation page off or back on for a first-party client.
     *
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

    /**
     * `POST /v1/oauth_clients/import` — bulk-import OAuth clients for a migration (idempotent per
     * client_id).
     *
     * @param list<mixed> $clients
     *
     * @return array<string,mixed>
     */
    public function import(array $clients, ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'POST',
            '/v1/oauth_clients/import',
            body: ['clients' => $clients],
            idempotencyKey: $idempotencyKey,
        );
    }
}
