<?php

declare(strict_types=1);

namespace Atlas\Resources;

/**
 * `/v1/iga` - 6 operations.
 */
final class IgaRequests extends Resource
{
    /**
     * `GET /v1/iga/requests` - List access requests, newest first.
     *
     * @param array{status?:'pending'|'approved'|'denied'|'cancelled'|'fulfilled'|'failed',user_id?:string,requester_user_id?:string,entitlement?:string,limit?:int,starting_after?:string} $params
     *
     * @return array<string,mixed>
     */
    public function list(array $params = []): array
    {
        return $this->http->request('GET', '/v1/iga/requests', query: $params);
    }

    /**
     * `POST /v1/iga/requests` - File an access request on behalf of a user. It follows the entitlement's
     * approval steps (and any extra step a separation-of-duties rule adds); with no steps it is fulfilled
     * at once. A blocking separation-of-duties conflict, a user who already holds the access, or a pending
     * duplicate is a 409. `duration_seconds` makes the grant time-bound (default and maximum: the
     * entitlement's limit).
     *
     * @param array{entitlement:string,user_id:string,requester_user_id?:string,justification?:string,duration_seconds?:int} $body
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>
     */
    public function create(array $body, array $params = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'POST',
            '/v1/iga/requests',
            body: $body,
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * `GET /v1/iga/requests/:id` - Read one request with its steps and the approvals recorded so far.
     *
     * @return array<string,mixed>
     */
    public function get(string $id): array
    {
        return $this->http->request('GET', '/v1/iga/requests/' . rawurlencode($id));
    }

    /**
     * `POST /v1/iga/requests/:id/decide` - Decide the open step. With `approver_user_id` the decision is
     * recorded as that person (who must be an approver of the step or hold a delegation); without it the
     * call is an administrator override that settles the step. A denial ends the request. 409 when it is
     * no longer pending.
     *
     * @param array{decision:'approve'|'deny',comment?:string,approver_user_id?:string} $body
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>
     */
    public function decide(string $id, array $body, array $params = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'POST',
            '/v1/iga/requests/' . rawurlencode($id) . '/decide',
            body: $body,
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * `POST /v1/iga/requests/:id/cancel` - Cancel a pending request.
     *
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>
     */
    public function cancel(string $id, array $params = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'POST',
            '/v1/iga/requests/' . rawurlencode($id) . '/cancel',
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * `GET /v1/iga/approvals` - The requests waiting on one person's decision, including those delegated
     * to them.
     *
     * @param array{approver_user_id?:string,limit?:int,starting_after?:string} $params
     *
     * @return array<string,mixed>
     */
    public function inbox(array $params = []): array
    {
        return $this->http->request('GET', '/v1/iga/approvals', query: $params);
    }
}
