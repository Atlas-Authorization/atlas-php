<?php

declare(strict_types=1);

namespace Atlas\Resources;

/** `/v1/rate_limit_policy` — the two per-instance rate-limit budgets. */
final class RateLimitPolicy extends Resource
{
    /** @return array<string,mixed> */
    public function get(): array
    {
        return $this->http->request('GET', '/v1/rate_limit_policy');
    }

    /**
     * @param array{fapi_create_per_min?:int,bapi_per_min?:int} $body
     *
     * @return array<string,mixed>
     */
    public function update(array $body): array
    {
        return $this->http->request('PATCH', '/v1/rate_limit_policy', body: $body);
    }
}
