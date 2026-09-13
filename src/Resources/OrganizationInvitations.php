<?php

declare(strict_types=1);

namespace Atlas\Resources;

/** `/v1/organizations/:id/invitations` — invitations into an organization. */
final class OrganizationInvitations extends Resource
{
    /** @return array<string,mixed> a ListPage of org invitations */
    public function list(string $orgId): array
    {
        return $this->http->request('GET', '/v1/organizations/' . rawurlencode($orgId) . '/invitations');
    }

    /**
     * @param array{email:string,role:string,inviter_user_id:string} $body
     *
     * @return array<string,mixed>
     */
    public function create(string $orgId, array $body, ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'POST',
            '/v1/organizations/' . rawurlencode($orgId) . '/invitations',
            body: $body,
            idempotencyKey: $idempotencyKey,
        );
    }

    /** @return array<string,mixed> */
    public function revoke(string $orgId, string $invitationId): array
    {
        return $this->http->request(
            'POST',
            '/v1/organizations/' . rawurlencode($orgId) . '/invitations/' . rawurlencode($invitationId) . '/revoke',
        );
    }
}
