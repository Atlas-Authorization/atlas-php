<?php

declare(strict_types=1);

namespace Atlas\Resources;

/**
 * `/v1/diagnostics` — explain why sign-in or a browser request fails.
 */
final class Diagnostics extends Resource
{
    /**
     * `GET /v1/diagnostics/login_readiness` — end-to-end login-readiness check for an origin (CORS, cookie
     * domain, email claim, signing keys). Pass live: true for a real key round trip.
     *
     * @param array<string,mixed> $params query parameters
     *
     * @return array<string,mixed>
     */
    public function loginReadiness(array $params = []): array
    {
        return $this->http->request('GET', '/v1/diagnostics/login_readiness', query: $params);
    }

    /**
     * `GET /v1/diagnostics/request_inspector` — explain why a browser request would be rejected (origin
     * required; method, path, token optional).
     *
     * @param array<string,mixed> $params query parameters
     *
     * @return array<string,mixed>
     */
    public function requestInspector(array $params = []): array
    {
        return $this->http->request('GET', '/v1/diagnostics/request_inspector', query: $params);
    }
}
