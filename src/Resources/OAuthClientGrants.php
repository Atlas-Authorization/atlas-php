<?php

declare(strict_types=1);

namespace Atlas\Resources;

/** `/v1/oauth_clients/:id/grants` — resource-server grants for a client. */
final class OAuthClientGrants extends Resource
{
    /** @return array<string,mixed> a ListPage of grants */
    public function list(string $clientId): array
    {
        return $this->http->request('GET', '/v1/oauth_clients/' . rawurlencode($clientId) . '/grants');
    }

    /**
     * @param array{resource_server_id:string,scopes?:list<string>} $body
     *
     * @return array<string,mixed>
     */
    public function create(string $clientId, array $body): array
    {
        return $this->http->request(
            'POST',
            '/v1/oauth_clients/' . rawurlencode($clientId) . '/grants',
            body: $body,
        );
    }

    /** @return array<string,mixed> */
    public function delete(string $clientId, string $grantId): array
    {
        return $this->http->request(
            'DELETE',
            '/v1/oauth_clients/' . rawurlencode($clientId) . '/grants/' . rawurlencode($grantId),
        );
    }
}
