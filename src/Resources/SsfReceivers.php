<?php

declare(strict_types=1);

namespace Atlas\Resources;

/**
 * `/v1/ssf/receivers` - 4 operations.
 */
final class SsfReceivers extends Resource
{
    /**
     * `GET /v1/ssf/receivers` - List inbound Shared Signals receivers: transmitters whose signed events
     * act on this instance's users.
     *
     * @param array{limit?:int,starting_after?:string} $params
     *
     * @return array<string,mixed>
     */
    public function list(array $params = []): array
    {
        return $this->http->request('GET', '/v1/ssf/receivers', query: $params);
    }

    /**
     * `POST /v1/ssf/receivers` - Register a transmitter. Its SETs are POSTed (`application/secevent+jwt`)
     * to the returned `receive_endpoint` and verified against `jwks_uri`, `issuer` and the optional
     * `audience`. `actions` maps an event type URI to `revoke_sessions`, `refresh_claims` (invalidate
     * minted tokens so the next refresh carries fresh claims, sessions stay), `ban` or `ignore`; unmapped
     * events are ignored. The default maps session-revoked, credential-change, credential-compromise and
     * device-compliance-change (only when the new status is not-compliant) to `revoke_sessions`,
     * token-claims-change to `refresh_claims` and account-disabled to `ban`.
     *
     * @param array{name?:string,issuer?:string,jwks_uri?:string,audience?:string,actions?:array<string,mixed>} $body
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>|null
     */
    public function create(array $body = [], array $params = [], ?string $idempotencyKey = null): ?array
    {
        return $this->http->request(
            'POST',
            '/v1/ssf/receivers',
            body: $body,
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * `PATCH /v1/ssf/receivers/:id` - Change a receiver: name, JWKS URI, audience, action mapping, or
     * enabled.
     *
     * @param array{name?:string,jwks_uri?:string,audience?:string,actions?:array<string,mixed>,enabled?:bool} $body
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>|null
     */
    public function update(string $id, array $body = [], array $params = [], ?string $idempotencyKey = null): ?array
    {
        return $this->http->request(
            'PATCH',
            '/v1/ssf/receivers/' . rawurlencode($id),
            body: $body,
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * `DELETE /v1/ssf/receivers/:id` - Delete an inbound SSF receiver; its endpoint stops accepting
     * events.
     *
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>|null
     */
    public function delete(string $id, array $params = [], ?string $idempotencyKey = null): ?array
    {
        return $this->http->request(
            'DELETE',
            '/v1/ssf/receivers/' . rawurlencode($id),
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }
}
