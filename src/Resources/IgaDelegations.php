<?php

declare(strict_types=1);

namespace Atlas\Resources;

/**
 * `/v1/iga/delegations` - 3 operations.
 */
final class IgaDelegations extends Resource
{
    /**
     * `GET /v1/iga/delegations` - List approval delegations.
     *
     * @param array{delegator_user_id?:string,limit?:int,starting_after?:string} $params
     *
     * @return array<string,mixed>
     */
    public function list(array $params = []): array
    {
        return $this->http->request('GET', '/v1/iga/delegations', query: $params);
    }

    /**
     * `POST /v1/iga/delegations` - Let another user decide a person's approvals (and reviews) for a period
     * of up to a year.
     *
     * @param array{delegator_user_id:string,delegate_user_id:string,starts_at?:int,ends_at:int} $body
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>
     */
    public function create(array $body, array $params = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'POST',
            '/v1/iga/delegations',
            body: $body,
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * `DELETE /v1/iga/delegations/:id` - End an approval delegation.
     *
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>
     */
    public function delete(string $id, array $params = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'DELETE',
            '/v1/iga/delegations/' . rawurlencode($id),
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }
}
