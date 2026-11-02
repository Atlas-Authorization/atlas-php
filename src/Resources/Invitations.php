<?php

declare(strict_types=1);

namespace Atlas\Resources;

/** `/v1/invitations` — instance-level sign-up invitations. */
final class Invitations extends Resource
{
    /**
     * @param array{limit?:int,starting_after?:string} $params
     *
     * @return array<string,mixed> a CursorPage of invitations
     */
    public function list(array $params = []): array
    {
        return $this->http->request('GET', '/v1/invitations', query: $params);
    }

    /**
     * `expires_at` (epoch ms or ISO 8601, at most 365 days ahead) or `expires_in_days` (1-365);
     * send one or the other. Default 7 days. Emits `invitation.created`.
     *
     * @param array{email_address:string,public_metadata?:array<string,mixed>,expires_at?:int|string,expires_in_days?:int} $body
     *
     * @return array<string,mixed>
     */
    public function create(array $body, ?string $idempotencyKey = null): array
    {
        return $this->http->request('POST', '/v1/invitations', body: $body, idempotencyKey: $idempotencyKey);
    }

    /** @return array<string,mixed> */
    public function revoke(string $id): array
    {
        return $this->http->request('POST', '/v1/invitations/' . rawurlencode($id) . '/revoke');
    }

    /**
     * `POST /v1/invitations/bulk` — create up to 100 invitations; returns created entries and per-entry
     * errors.
     *
     * @param list<mixed> $invitations
     *
     * @return array<string,mixed>
     */
    public function bulkCreate(array $invitations): array
    {
        return $this->http->request('POST', '/v1/invitations/bulk', body: ['invitations' => $invitations]);
    }
}
