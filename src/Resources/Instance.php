<?php

declare(strict_types=1);

namespace Atlas\Resources;

/**
 * `/v1/instance` — §9.3 instance configuration. The instance is the tenant's
 * environment (its publishable key, frontend API host, allowed origins and auth
 * config). The signing private key is never returned. JWT templates live under
 * their own {@see JwtTemplates} resource, not here.
 */
final class Instance extends Resource
{
    /** @return array<string,mixed> the Instance object */
    public function get(): array
    {
        return $this->http->request('GET', '/v1/instance');
    }

    /**
     * `PATCH /v1/instance` echoes only the mutable fields, not a full Instance.
     *
     * @param array{allowed_origins?:list<string>,auth_config?:array<string,mixed>} $body
     *
     * @return array<string,mixed>
     */
    public function update(array $body): array
    {
        return $this->http->request('PATCH', '/v1/instance', body: $body);
    }
}
