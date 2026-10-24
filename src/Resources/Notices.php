<?php

declare(strict_types=1);

namespace Atlas\Resources;

/**
 * `/v1/instance/notices` — notices from Atlas about the instance (deprecations, changes), with acknowledgement.
 */
final class Notices extends Resource
{
    /**
     * Notices; `['all' => true]` includes the acknowledged. Adds `unacknowledged` and `changelog_url`.
     *
     * @return array<string,mixed>
     */
    public function list(array $query = []): array
    {
        return $this->http->request('GET', '/v1/instance/notices', query: $query);
    }

    /**
     * Acknowledge a notice.
     *
     * @return array<string,mixed>
     */
    public function acknowledge(string $id): array
    {
        return $this->http->request('POST', '/v1/instance/notices/' . rawurlencode($id) . '/acknowledge');
    }
}
