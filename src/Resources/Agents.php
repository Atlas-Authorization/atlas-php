<?php

declare(strict_types=1);

namespace Atlas\Resources;

/**
 * `/v1/agents` - 5 operations.
 */
final class Agents extends Resource
{
    /**
     * `POST /v1/agents/:id/claim` - Backend-assisted claim: bind an agent to a user you already know and
     * promote it to trusted. Audited as agent.claimed.
     *
     * @param array{user_id:string} $body
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>|null
     */
    public function claim(string $id, array $body, array $params = [], ?string $idempotencyKey = null): ?array
    {
        return $this->http->request(
            'POST',
            '/v1/agents/' . rawurlencode($id) . '/claim',
            body: $body,
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * `GET /v1/agents` - The agent registry: every registered agent with its tier, owner and status.
     * Filter by owner_user_id or tier; page with limit and starting_after.
     *
     * @param array{owner_user_id?:string,tier?:'untrusted'|'trusted',limit?:int,starting_after?:string} $params
     *
     * @return array<string,mixed>
     */
    public function list(array $params = []): array
    {
        return $this->http->request('GET', '/v1/agents', query: $params);
    }

    /**
     * `GET /v1/agents/:id` - One agent with its currently valid access grants.
     *
     * @return array<string,mixed>
     */
    public function get(string $id): array
    {
        return $this->http->request('GET', '/v1/agents/' . rawurlencode($id));
    }

    /**
     * `PUT /v1/agents/:id/owner` - Set the owning user of an agent, or null to remove the owner (which
     * also drops the agent back to untrusted).
     *
     * @param array{user_id:string|null} $body
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>|null
     */
    public function setOwner(string $id, array $body, array $params = [], ?string $idempotencyKey = null): ?array
    {
        return $this->http->request(
            'PUT',
            '/v1/agents/' . rawurlencode($id) . '/owner',
            body: $body,
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * `POST /v1/agents/:id/revoke_access` - Revoke every grant and cancel every pending request of an
     * agent. With disable: true the agent itself is revoked and can no longer get tokens. Emits
     * agent.access_revoked.
     *
     * @param array{disable?:bool} $body
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>|null
     */
    public function revokeAccess(string $id, array $body = [], array $params = [], ?string $idempotencyKey = null): ?array
    {
        return $this->http->request(
            'POST',
            '/v1/agents/' . rawurlencode($id) . '/revoke_access',
            body: $body,
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }
}
