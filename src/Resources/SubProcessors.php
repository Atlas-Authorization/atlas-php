<?php

declare(strict_types=1);

namespace Atlas\Resources;

/**
 * `GET /v1/instance/sub_processors` — the sub-processors Atlas uses for this instance (name, role, url) for your DPA.
 */
final class SubProcessors extends Resource
{
    /**
     * The sub-processor list.
     *
     * @return array<string,mixed>
     */
    public function get(): array
    {
        return $this->http->request('GET', '/v1/instance/sub_processors');
    }
}
