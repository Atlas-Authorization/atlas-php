<?php

declare(strict_types=1);

namespace Atlas\Resources;

/** `/v1/localizations` — per-locale overrides for emails, SMS and prompt copy. */
final class Localizations extends Resource
{
    /**
     * @param array{resource_type?:string,resource_name?:string,locale?:string} $params
     *
     * @return array<string,mixed> a ListPage of localizations
     */
    public function list(array $params = []): array
    {
        return $this->http->request('GET', '/v1/localizations', query: $params);
    }

    /**
     * @param array{resource_type:string,resource_name:string,locale:string,content:array<string,mixed>,enabled?:bool} $body
     *
     * @return array<string,mixed>
     */
    public function create(array $body, ?string $idempotencyKey = null): array
    {
        return $this->http->request('POST', '/v1/localizations', body: $body, idempotencyKey: $idempotencyKey);
    }

    /** @return array<string,mixed> */
    public function get(string $id): array
    {
        return $this->http->request('GET', '/v1/localizations/' . rawurlencode($id));
    }

    /**
     * @param array{content?:array<string,mixed>,enabled?:bool} $body
     *
     * @return array<string,mixed>
     */
    public function update(string $id, array $body): array
    {
        return $this->http->request('PATCH', '/v1/localizations/' . rawurlencode($id), body: $body);
    }

    /** @return null on 204 */
    public function delete(string $id): mixed
    {
        return $this->http->request('DELETE', '/v1/localizations/' . rawurlencode($id));
    }
}
