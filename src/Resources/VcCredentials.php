<?php

declare(strict_types=1);

namespace Atlas\Resources;

/**
 * `/v1/vc/credentials` - 2 operations.
 */
final class VcCredentials extends Resource
{
    /**
     * `GET /v1/vc/credentials` - List issued credentials (most recent 200), optionally for one user.
     *
     * @param array{user_id?:string,limit?:int,starting_after?:string} $params
     *
     * @return array<string,mixed>
     */
    public function list(array $params = []): array
    {
        return $this->http->request('GET', '/v1/vc/credentials', query: $params);
    }

    /**
     * `POST /v1/vc/credentials/:id/revoke` - Revoke an issued credential: its status-list bit is set and
     * later presentations are refused.
     *
     * @param array<string,mixed> $body
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>
     */
    public function revoke(string $id, array $body, array $params = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'POST',
            '/v1/vc/credentials/' . rawurlencode($id) . '/revoke',
            body: $body,
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }
}
