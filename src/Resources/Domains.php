<?php

declare(strict_types=1);

namespace Atlas\Resources;

/** `/v1/domains` — custom FAPI/accounts domains and their CNAME verification. */
final class Domains extends Resource
{
    /** @return array<string,mixed> a list envelope with CNAME instructions */
    public function list(): array
    {
        return $this->http->request('GET', '/v1/domains');
    }

    /**
     * @param array{host:string,role?:string} $body
     *
     * @return array<string,mixed>
     */
    public function create(array $body, ?string $idempotencyKey = null): array
    {
        return $this->http->request('POST', '/v1/domains', body: $body, idempotencyKey: $idempotencyKey);
    }

    /** @return array<string,mixed> */
    public function verify(string $id): array
    {
        return $this->http->request('POST', '/v1/domains/' . rawurlencode($id) . '/verify');
    }

    /** @return array<string,mixed> */
    public function delete(string $id): array
    {
        return $this->http->request('DELETE', '/v1/domains/' . rawurlencode($id));
    }
}
