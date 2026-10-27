<?php

declare(strict_types=1);

namespace Atlas\Resources;

/**
 * `/v1/grant_management/grants` - 4 operations.
 */
final class ManagedGrants extends Resource
{
    /**
     * `GET /v1/grant_management/grants` - List OpenID Grant Management grants: what each OAuth client
     * holds for each user under a `grant_id`, newest first. Filter by `user_id`, `client_id` (the public
     * client id) and `status`.
     *
     * @param array{user_id?:string,client_id?:string,status?:'active'|'revoked',limit?:int,starting_after?:string} $params
     *
     * @return array<string,mixed>
     */
    public function list(array $params = []): array
    {
        return $this->http->request('GET', '/v1/grant_management/grants', query: $params);
    }

    /**
     * `GET /v1/grant_management/grants/:id` - Read one grant: its scopes, resource, authorization_details,
     * status and revision.
     *
     * @return array<string,mixed>
     */
    public function get(string $id): array
    {
        return $this->http->request('GET', '/v1/grant_management/grants/' . rawurlencode($id));
    }

    /**
     * `PATCH /v1/grant_management/grants/:id` - Narrow a grant: send the `scopes` and/or
     * `authorization_details` it should keep. Anything it does not already hold is a 400 (only the user
     * widens a grant, by authorizing again with `grant_management_action=merge`). Refresh tokens under the
     * grant are clamped to the narrower scope; a revoked grant is a 404.
     *
     * @param array{scopes?:list<string>,authorization_details?:list<array<string,mixed>>} $body
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>
     */
    public function narrow(string $id, array $body = [], array $params = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'PATCH',
            '/v1/grant_management/grants/' . rawurlencode($id),
            body: $body,
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * `DELETE /v1/grant_management/grants/:id` - Revoke a grant and everything issued under it: refresh
     * tokens stop refreshing and access tokens that carry the `grant_id` stop validating at once.
     * `tokens_ended` counts the refresh grants that ended with it. Revoking an already-revoked grant
     * answers 200 with `tokens_ended: 0`.
     *
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>
     */
    public function revoke(string $id, array $params = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'DELETE',
            '/v1/grant_management/grants/' . rawurlencode($id),
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }
}
