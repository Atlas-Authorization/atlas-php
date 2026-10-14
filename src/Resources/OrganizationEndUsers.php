<?php

declare(strict_types=1);

namespace Atlas\Resources;

/**
 * `/v1/organizations/:orgId/end_users` — the organization's end-user (reader) pool.
 */
final class OrganizationEndUsers extends Resource
{
    /**
     * `GET /v1/organizations/:orgId/end_users` — the organization's end-user (reader) pool. Filter with
     * status. Cursor-paginated.
     *
     * @param array<string,mixed> $params query parameters
     *
     * @return array<string,mixed>
     */
    public function list(string $organizationId, array $params = []): array
    {
        return $this->http->request(
            'GET',
            '/v1/organizations/' . rawurlencode($organizationId) . '/end_users',
            query: $params,
        );
    }

    /**
     * `POST /v1/organizations/:orgId/end_users` — attach a user to the organization's end-user pool.
     *
     * @param array<string,mixed> $body
     *
     * @return array<string,mixed>
     */
    public function add(string $organizationId, array $body, ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'POST',
            '/v1/organizations/' . rawurlencode($organizationId) . '/end_users',
            body: $body,
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * `PATCH /v1/organizations/:orgId/end_users/:userId` — approve (active), gate (pending) or block an
     * end user.
     *
     * @param array<string,mixed> $body
     *
     * @return array<string,mixed>
     */
    public function update(string $organizationId, string $userId, array $body): array
    {
        return $this->http->request(
            'PATCH',
            '/v1/organizations/' . rawurlencode($organizationId) . '/end_users/' . rawurlencode($userId),
            body: $body,
        );
    }

    /**
     * `DELETE /v1/organizations/:orgId/end_users/:userId` — remove an end user from the pool.
     *
     * @return array<string,mixed>
     */
    public function remove(string $organizationId, string $userId): array
    {
        return $this->http->request(
            'DELETE',
            '/v1/organizations/' . rawurlencode($organizationId) . '/end_users/' . rawurlencode($userId),
        );
    }
}
