<?php

declare(strict_types=1);

namespace Atlas\Resources;

/** `/v1/radius_clients` — RADIUS NAS clients (shared secret write-only). */
final class RadiusClients extends Resource
{
    /** @return array<string,mixed> a ListPage of clients */
    public function list(): array
    {
        return $this->http->request('GET', '/v1/radius_clients');
    }

    /**
     * @param array{name:string,nas_identifier:string,shared_secret:string,ip_address?:?string,require_message_authenticator?:bool,enabled?:bool} $body
     *
     * @return array<string,mixed>
     */
    public function create(array $body, ?string $idempotencyKey = null): array
    {
        return $this->http->request('POST', '/v1/radius_clients', body: $body, idempotencyKey: $idempotencyKey);
    }

    /** @return array<string,mixed> */
    public function get(string $id): array
    {
        return $this->http->request('GET', '/v1/radius_clients/' . rawurlencode($id));
    }

    /**
     * @param array<string,mixed> $body omit `shared_secret` to keep the stored one
     *
     * @return array<string,mixed>
     */
    public function update(string $id, array $body): array
    {
        return $this->http->request('PATCH', '/v1/radius_clients/' . rawurlencode($id), body: $body);
    }

    /** @return array<string,mixed> */
    public function delete(string $id): array
    {
        return $this->http->request('DELETE', '/v1/radius_clients/' . rawurlencode($id));
    }
}
