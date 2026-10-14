<?php

declare(strict_types=1);

namespace Atlas\Resources;

/**
 * `/v1/events` — the product activity feed.
 */
final class Events extends Resource
{
    /**
     * `GET /v1/events` — the product activity feed, filterable by type and time. Cursor-paginated.
     *
     * @param array<string,mixed> $params query parameters
     *
     * @return array<string,mixed>
     */
    public function list(array $params = []): array
    {
        return $this->http->request('GET', '/v1/events', query: $params);
    }
}
