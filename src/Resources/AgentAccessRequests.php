<?php

declare(strict_types=1);

namespace Atlas\Resources;

/**
 * `/v1/agent_access_requests` - 3 operations.
 */
final class AgentAccessRequests extends Resource
{
    /**
     * `POST /v1/agent_access_requests/:id/approve` - Approve a pending request, optionally for a limited
     * time (expires_in_seconds). 409 when it is no longer pending. Emits agent.access_decided.
     *
     * @param array{expires_in_seconds?:int,decided_by?:string} $body
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>|null
     */
    public function approve(string $id, array $body = [], array $params = [], ?string $idempotencyKey = null): ?array
    {
        return $this->http->request(
            'POST',
            '/v1/agent_access_requests/' . rawurlencode($id) . '/approve',
            body: $body,
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * `POST /v1/agent_access_requests/:id/deny` - Deny a pending request. 409 when it is no longer
     * pending. Emits agent.access_decided.
     *
     * @param array{decided_by?:string} $body
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>|null
     */
    public function deny(string $id, array $body = [], array $params = [], ?string $idempotencyKey = null): ?array
    {
        return $this->http->request(
            'POST',
            '/v1/agent_access_requests/' . rawurlencode($id) . '/deny',
            body: $body,
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * `GET /v1/agent_access_requests` - List agent access requests, newest first. Filter by status
     * (pending, approved, denied, revoked) or agent_id.
     *
     * @param array{status?:'pending'|'approved'|'denied'|'revoked',agent_id?:string,limit?:int,starting_after?:string} $params
     *
     * @return array<string,mixed>
     */
    public function list(array $params = []): array
    {
        return $this->http->request('GET', '/v1/agent_access_requests', query: $params);
    }
}
