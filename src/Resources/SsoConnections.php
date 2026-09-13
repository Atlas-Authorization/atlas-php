<?php

declare(strict_types=1);

namespace Atlas\Resources;

/** `/v1/sso_connections` — enterprise OIDC/SAML/Discourse SSO connections. */
final class SsoConnections extends Resource
{
    /** @return array<string,mixed> a ListPage of connections */
    public function list(): array
    {
        return $this->http->request('GET', '/v1/sso_connections');
    }

    /** @return array<string,mixed> */
    public function get(string $id): array
    {
        return $this->http->request('GET', '/v1/sso_connections/' . rawurlencode($id));
    }

    /**
     * @param array<string,mixed> $body write-only secrets never come back
     *
     * @return array<string,mixed>
     */
    public function create(array $body, ?string $idempotencyKey = null): array
    {
        return $this->http->request('POST', '/v1/sso_connections', body: $body, idempotencyKey: $idempotencyKey);
    }

    /**
     * PATCH accepts everything create does except `type`, which is immutable.
     *
     * @param array<string,mixed> $body
     *
     * @return array<string,mixed>
     */
    public function update(string $id, array $body): array
    {
        return $this->http->request('PATCH', '/v1/sso_connections/' . rawurlencode($id), body: $body);
    }

    /** @return array<string,mixed> */
    public function delete(string $id): array
    {
        return $this->http->request('DELETE', '/v1/sso_connections/' . rawurlencode($id));
    }

    /** SP SAML metadata for a connection (JSON envelope carrying the XML). @return array<string,mixed> */
    public function samlMetadata(string $id): array
    {
        return $this->http->request('GET', '/v1/sso_connections/' . rawurlencode($id) . '/saml_metadata');
    }
}
