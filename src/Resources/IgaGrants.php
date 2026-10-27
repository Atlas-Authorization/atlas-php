<?php

declare(strict_types=1);

namespace Atlas\Resources;

/**
 * `/v1/iga/grants` - 3 operations.
 */
final class IgaGrants extends Resource
{
    /**
     * `GET /v1/iga/grants` - List grants (what each user was given through governance), newest first.
     *
     * @param array{user_id?:string,entitlement?:string,status?:'active'|'revoked'|'expired',limit?:int,starting_after?:string} $params
     *
     * @return array<string,mixed>
     */
    public function list(array $params = []): array
    {
        return $this->http->request('GET', '/v1/iga/grants', query: $params);
    }

    /**
     * `POST /v1/iga/grants` - Give a user an entitlement now, without a request. `expires_at` makes it
     * time-bound. A blocking separation-of-duties rule refuses it; a require-approval rule refuses unless
     * `override_reason` is given, which records an accepted violation.
     *
     * @param array{entitlement:string,user_id:string,expires_at?:int,override_reason?:string} $body
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>
     */
    public function create(array $body, array $params = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'POST',
            '/v1/iga/grants',
            body: $body,
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * `POST /v1/iga/grants/:id/revoke` - Take the access away and close the grant, including access that
     * existed before governance adopted it (an expiry, by contrast, leaves adopted access in place).
     *
     * @param array{reason?:string} $body
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>
     */
    public function revoke(string $id, array $body = [], array $params = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'POST',
            '/v1/iga/grants/' . rawurlencode($id) . '/revoke',
            body: $body,
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }
}
