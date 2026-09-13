<?php

declare(strict_types=1);

namespace Atlas\Resources;

/** `/v1/resource_servers` — API resource servers (audiences) and their scopes. */
final class ResourceServers extends Resource
{
    /** @return array<string,mixed> a ListPage of resource servers */
    public function list(): array
    {
        return $this->http->request('GET', '/v1/resource_servers');
    }

    /** @return array<string,mixed> */
    public function get(string $id): array
    {
        return $this->http->request('GET', '/v1/resource_servers/' . rawurlencode($id));
    }

    /**
     * @param array{identifier:string,name:string,scopes?:list<mixed>,token_ttl_seconds?:int,signing_alg?:string} $body
     *
     * @return array<string,mixed>
     */
    public function create(array $body): array
    {
        return $this->http->request('POST', '/v1/resource_servers', body: $body);
    }

    /**
     * @param array<string,mixed> $body
     *
     * @return array<string,mixed>
     */
    public function update(string $id, array $body): array
    {
        return $this->http->request('PATCH', '/v1/resource_servers/' . rawurlencode($id), body: $body);
    }

    /** @return array<string,mixed> */
    public function delete(string $id): array
    {
        return $this->http->request('DELETE', '/v1/resource_servers/' . rawurlencode($id));
    }
}
