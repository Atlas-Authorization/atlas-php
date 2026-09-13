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
     * @param array{email_address:string,public_metadata?:array<string,mixed>} $body
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
}
