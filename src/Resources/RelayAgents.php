<?php

declare(strict_types=1);

namespace Atlas\Resources;

/**
 * `/v1/relay_agents` - 6 operations.
 */
final class RelayAgents extends Resource
{
    /**
     * `GET /v1/relay_agents` - List the on-prem relay agents with their allowed destinations and whether
     * each is online. Secrets are never returned.
     *
     * @param array{limit?:int,starting_after?:string} $params
     *
     * @return array<string,mixed>
     */
    public function list(array $params = []): array
    {
        return $this->http->request('GET', '/v1/relay_agents', query: $params);
    }

    /**
     * `POST /v1/relay_agents` - Register an outbound-only relay agent. The response carries the agent
     * token once; it cannot be read back. An agent can reach only the destinations in allowed_destinations
     * (host, host:port, *.domain).
     *
     * @param array{name:string,organization_id?:string|null,status?:'active'|'revoked',allowed_destinations?:list<string>,cert_fingerprints?:list<string>} $body
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>
     */
    public function create(array $body = [], array $params = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'POST',
            '/v1/relay_agents',
            body: $body,
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * `GET /v1/relay_agents/:id` - Read one relay agent, including when it last polled.
     *
     * @return array<string,mixed>
     */
    public function get(string $id): array
    {
        return $this->http->request('GET', '/v1/relay_agents/' . rawurlencode($id));
    }

    /**
     * `PATCH /v1/relay_agents/:id` - Rename an agent, change its allowed destinations or pinned
     * client-certificate thumbprints, or revoke it (status: revoked).
     *
     * @param array{name?:string,organization_id?:string|null,status?:'active'|'revoked',allowed_destinations?:list<string>,cert_fingerprints?:list<string>} $body
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>
     */
    public function update(string $id, array $body = [], array $params = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'PATCH',
            '/v1/relay_agents/' . rawurlencode($id),
            body: $body,
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * `DELETE /v1/relay_agents/:id` - Delete a relay agent and its queued jobs.
     *
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>|null
     */
    public function delete(string $id, array $params = [], ?string $idempotencyKey = null): ?array
    {
        return $this->http->request(
            'DELETE',
            '/v1/relay_agents/' . rawurlencode($id),
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * `POST /v1/relay_agents/:id/rotate_secret` - Issue a new agent token (returned once). The old token
     * stops working immediately.
     *
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>
     */
    public function rotateSecret(string $id, array $params = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'POST',
            '/v1/relay_agents/' . rawurlencode($id) . '/rotate_secret',
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }
}
