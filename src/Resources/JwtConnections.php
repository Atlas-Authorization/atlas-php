<?php

declare(strict_types=1);

namespace Atlas\Resources;

/**
 * `/v1/jwt_connections` — trusted-JWT connections: your system signs a short-lived JWT
 * (asymmetric keys from your `jwks_uri`) and sends the person to the connection's
 * `login_url` with `?token=<jwt>&redirect_url=<your app URL>`.
 */
final class JwtConnections extends Resource
{
    /** @return array<string,mixed> a ListPage of jwt_connection objects */
    public function list(): array
    {
        return $this->http->request('GET', '/v1/jwt_connections');
    }

    /** @return array<string,mixed> */
    public function get(string $id): array
    {
        return $this->http->request('GET', '/v1/jwt_connections/' . rawurlencode($id));
    }

    /**
     * `max_age_seconds` caps exp − iat (30–3600, default 300); `email_claim` defaults to `email`.
     *
     * @param array{organization_id?:?string,status?:'draft'|'active'|'disabled',jwks_uri?:string,issuer?:string,audience?:string,email_claim?:string,max_age_seconds?:int,default_role_id?:?string} $body
     *
     * @return array<string,mixed>
     */
    public function create(array $body, ?string $idempotencyKey = null): array
    {
        return $this->http->request('POST', '/v1/jwt_connections', body: $body, idempotencyKey: $idempotencyKey);
    }

    /**
     * @param array{organization_id?:?string,status?:'draft'|'active'|'disabled',jwks_uri?:string,issuer?:string,audience?:string,email_claim?:string,max_age_seconds?:int,default_role_id?:?string} $body
     *
     * @return array<string,mixed>
     */
    public function update(string $id, array $body): array
    {
        return $this->http->request('PATCH', '/v1/jwt_connections/' . rawurlencode($id), body: $body);
    }

    /** @return array<string,mixed> a DeletedObject */
    public function delete(string $id): array
    {
        return $this->http->request('DELETE', '/v1/jwt_connections/' . rawurlencode($id));
    }
}
