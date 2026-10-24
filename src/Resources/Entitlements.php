<?php

declare(strict_types=1);

namespace Atlas\Resources;

/**
 * `GET /v1/entitlements` — the resolved entitlements of a subject (plan, add-ons, overrides). Overrides are on {@see EntitlementOverrides} (`internal` flag on create).
 */
final class Entitlements extends Resource
{
    /**
     * Entitlements for the subject named in `$query` (`user_id`, `organization_id`, `feature`, ...).
     *
     * @return array<string,mixed>
     */
    public function list(array $query = []): array
    {
        return $this->http->request('GET', '/v1/entitlements', query: $query);
    }
}
