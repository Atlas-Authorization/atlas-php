<?php

declare(strict_types=1);

namespace Atlas\Resources;

/** `/v1/organizations/:id/memberships` — the members of an organization. */
final class OrganizationMemberships extends Resource
{
    /** @return array<string,mixed> */
    public function list(string $orgId): array
    {
        return $this->http->request('GET', '/v1/organizations/' . rawurlencode($orgId) . '/memberships');
    }

    /**
     * @param array{user_id:string,role?:string} $body
     *
     * @return array<string,mixed>
     */
    public function add(string $orgId, array $body, ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'POST',
            '/v1/organizations/' . rawurlencode($orgId) . '/memberships',
            body: $body,
            idempotencyKey: $idempotencyKey,
        );
    }

    /** @return array<string,mixed> */
    public function update(string $orgId, string $userId, string $role): array
    {
        return $this->http->request(
            'PATCH',
            '/v1/organizations/' . rawurlencode($orgId) . '/memberships/' . rawurlencode($userId),
            body: ['role' => $role],
        );
    }

    /** @return array<string,mixed> */
    public function remove(string $orgId, string $userId): array
    {
        return $this->http->request(
            'DELETE',
            '/v1/organizations/' . rawurlencode($orgId) . '/memberships/' . rawurlencode($userId),
        );
    }
}
