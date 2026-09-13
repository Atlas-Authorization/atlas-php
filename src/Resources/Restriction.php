<?php

declare(strict_types=1);

namespace Atlas\Resources;

use Atlas\Http;

/**
 * Allowlist and blocklist share an identical route shape, so they share a class.
 * The base path (`/v1/allowlist_identifiers` or `/v1/blocklist_identifiers`)
 * differs; nothing else does.
 */
final class Restriction extends Resource
{
    public function __construct(Http $http, private readonly string $path)
    {
        parent::__construct($http);
    }

    /** @return array<string,mixed> a list envelope of identifiers */
    public function list(): array
    {
        return $this->http->request('GET', $this->path);
    }

    /** @return array<string,mixed> */
    public function add(string $identifier): array
    {
        return $this->http->request('POST', $this->path, body: ['identifier' => $identifier]);
    }

    /** @return array<string,mixed> */
    public function remove(string $id): array
    {
        return $this->http->request('DELETE', $this->path . '/' . rawurlencode($id));
    }
}
