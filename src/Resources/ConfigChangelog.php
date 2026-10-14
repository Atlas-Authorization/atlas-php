<?php

declare(strict_types=1);

namespace Atlas\Resources;

/**
 * `/v1/config_changelog` — who changed which configuration, and when.
 */
final class ConfigChangelog extends Resource
{
    /**
     * `GET /v1/config_changelog` — configuration change history (who changed what, when, with a diff).
     * Cursor-paginated.
     *
     * @param array<string,mixed> $params query parameters
     *
     * @return array<string,mixed>
     */
    public function list(array $params = []): array
    {
        return $this->http->request('GET', '/v1/config_changelog', query: $params);
    }
}
