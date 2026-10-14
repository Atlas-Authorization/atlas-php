<?php

declare(strict_types=1);

namespace Atlas\Resources;

/**
 * `/v1/usage` — usage analytics.
 */
final class Usage extends Resource
{
    /**
     * `GET /v1/usage` — usage analytics: daily sign-in and active-user series plus monthly active users.
     *
     * @param array<string,mixed> $params query parameters
     *
     * @return array<string,mixed>
     */
    public function get(array $params = []): array
    {
        return $this->http->request('GET', '/v1/usage', query: $params);
    }
}
