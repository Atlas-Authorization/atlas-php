<?php

declare(strict_types=1);

namespace Atlas\Resources;

use Atlas\Http;

/**
 * `/v1/authz_decisions` - 3 operations.
 */
final class AuthzDecisions extends Resource
{
    public readonly AuthzDecisionsSettings $settings;

    public function __construct(Http $http)
    {
        parent::__construct($http);
        $this->settings = new AuthzDecisionsSettings($http);
    }

    /**
     * `GET /v1/authz_decisions` - Query the authorization decision log, newest first: ABAC, AuthZEN,
     * FGA-check and access-policy decisions with their redacted input, result, the policy revision in
     * force and latency. Only decisions that passed sampling are stored.
     *
     * @param array{source?:'abac'|'authzen'|'fga'|'access_policy'|'mcp_gateway',decision?:'allow'|'deny',principal?:string,action?:string,resource?:string,policy_revision?:string,since?:string,until?:string,limit?:int,starting_after?:string} $params
     *
     * @return array<string,mixed>
     */
    public function list(array $params = []): array
    {
        return $this->http->request('GET', '/v1/authz_decisions', query: $params);
    }

    /**
     * `GET /v1/authz_decisions/stats` - Decision counts per engine and outcome over a window (the last 24
     * hours by default), with a sampling-corrected estimate of the true volume and latency percentiles of
     * the recorded decisions.
     *
     * @param array{since?:string,until?:string} $params
     *
     * @return array<string,mixed>
     */
    public function stats(array $params = []): array
    {
        return $this->http->request('GET', '/v1/authz_decisions/stats', query: $params);
    }

    /**
     * `GET /v1/authz_decisions/:id` - Read one logged decision.
     *
     * @return array<string,mixed>
     */
    public function get(string $id): array
    {
        return $this->http->request('GET', '/v1/authz_decisions/' . rawurlencode($id));
    }
}
