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
     * @param array{type?:string,since?:int|string,until?:int|string,order?:'asc'|'desc',limit?:int,starting_after?:string} $params
     *   `since` / `until` bound the window (epoch ms or ISO 8601); the page reports `retention`
     *
     * @return array<string,mixed>
     */
    public function list(array $params = []): array
    {
        return $this->http->request('GET', '/v1/events', query: $params);
    }
}
