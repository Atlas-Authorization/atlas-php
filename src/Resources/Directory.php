<?php

declare(strict_types=1);

namespace Atlas\Resources;

/**
 * `/v1/directory` — the unified connector directory.
 */
final class Directory extends Resource
{
    /**
     * `GET /v1/directory` — the connector directory: built-in providers plus published community
     * connectors.
     *
     * @param array<string,mixed> $params query parameters
     *
     * @return array<string,mixed>
     */
    public function list(array $params = []): array
    {
        return $this->http->request('GET', '/v1/directory', query: $params);
    }
}
