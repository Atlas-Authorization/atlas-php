<?php

declare(strict_types=1);

namespace Atlas\Resources;

/** `/v1/network_acls` — per-instance IP allow/deny rules, evaluated by priority. */
final class NetworkAcls extends Resource
{
    /** @return array<string,mixed> a ListPage of rules, in priority order */
    public function list(): array
    {
        return $this->http->request('GET', '/v1/network_acls');
    }

    /** @return array<string,mixed> */
    public function get(string $id): array
    {
        return $this->http->request('GET', '/v1/network_acls/' . rawurlencode($id));
    }

    /**
     * @param array{action:string,cidr:string,description?:?string,priority?:int,enabled?:bool} $body
     *
     * @return array<string,mixed>
     */
    public function create(array $body, ?string $idempotencyKey = null): array
    {
        return $this->http->request('POST', '/v1/network_acls', body: $body, idempotencyKey: $idempotencyKey);
    }

    /**
     * @param array<string,mixed> $body
     *
     * @return array<string,mixed>
     */
    public function update(string $id, array $body): array
    {
        return $this->http->request('PATCH', '/v1/network_acls/' . rawurlencode($id), body: $body);
    }

    /** @return array<string,mixed> */
    public function delete(string $id): array
    {
        return $this->http->request('DELETE', '/v1/network_acls/' . rawurlencode($id));
    }
}
