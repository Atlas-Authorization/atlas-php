<?php

declare(strict_types=1);

namespace Atlas\Resources;

/**
 * `/v1/ssf/streams` - 9 operations.
 */
final class SsfStreams extends Resource
{
    /**
     * `GET /v1/ssf/streams` - List the Shared Signals streams this instance transmits on (push and poll),
     * with their delivery method, requested events and status.
     *
     * @param array{limit?:int,starting_after?:string} $params
     *
     * @return array<string,mixed>
     */
    public function list(array $params = []): array
    {
        return $this->http->request('GET', '/v1/ssf/streams', query: $params);
    }

    /**
     * `POST /v1/ssf/streams` - Create a stream. `delivery_method` is `push` (we POST each signed SET to
     * `endpoint_url`, with the optional `authorization_header`) or `poll` (the receiver pulls from the
     * returned `poll_endpoint` with the one-time `poll_token`). `events_requested` limits the events sent
     * (empty = all supported). SETs are signed with the instance signing key.
     *
     * @param array{delivery_method?:'push'|'poll',endpoint_url?:string,authorization_header?:string,audience?:string,description?:string,events_requested?:list<string>} $body
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>|null
     */
    public function create(array $body = [], array $params = [], ?string $idempotencyKey = null): ?array
    {
        return $this->http->request(
            'POST',
            '/v1/ssf/streams',
            body: $body,
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * `GET /v1/ssf/streams/:id` - Read a stream, including how many events are waiting to be delivered or
     * acknowledged.
     *
     * @return array<string,mixed>
     */
    public function get(string $id): array
    {
        return $this->http->request('GET', '/v1/ssf/streams/' . rawurlencode($id));
    }

    /**
     * `PATCH /v1/ssf/streams/:id` - Change a stream: its events, endpoint, authorization header,
     * description, or status (`enabled`, `paused`, `disabled`). A paused stream stops receiving new
     * events.
     *
     * @param array{endpoint_url?:string,authorization_header?:string,description?:string,events_requested?:list<string>,status?:'enabled'|'paused'|'disabled'} $body
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>|null
     */
    public function update(string $id, array $body = [], array $params = [], ?string $idempotencyKey = null): ?array
    {
        return $this->http->request(
            'PATCH',
            '/v1/ssf/streams/' . rawurlencode($id),
            body: $body,
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * `DELETE /v1/ssf/streams/:id` - Delete a stream and its queued events.
     *
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>|null
     */
    public function delete(string $id, array $params = [], ?string $idempotencyKey = null): ?array
    {
        return $this->http->request(
            'DELETE',
            '/v1/ssf/streams/' . rawurlencode($id),
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * `POST /v1/ssf/streams/:id/verify` - Queue an SSF verification event carrying `state`, so a receiver
     * can confirm the stream works end to end.
     *
     * @param array{state?:string} $body
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>|null
     */
    public function verify(string $id, array $body = [], array $params = [], ?string $idempotencyKey = null): ?array
    {
        return $this->http->request(
            'POST',
            '/v1/ssf/streams/' . rawurlencode($id) . '/verify',
            body: $body,
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * `GET /v1/ssf/streams/:id/status` - The stream's delivery status: enabled, paused or disabled (SSF
     * stream status).
     *
     * @return array<string,mixed>
     */
    public function getStatus(string $id): array
    {
        return $this->http->request('GET', '/v1/ssf/streams/' . rawurlencode($id) . '/status');
    }

    /**
     * `POST /v1/ssf/streams/:id/status` - Set the stream's delivery status to enabled, paused or disabled.
     * A paused stream collects nothing.
     *
     * @param array{status?:'enabled'|'paused'|'disabled'} $body
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>
     */
    public function updateStatus(string $id, array $body = [], array $params = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'POST',
            '/v1/ssf/streams/' . rawurlencode($id) . '/status',
            body: $body,
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * `GET /v1/ssf/streams/:id/events` - The stream's recent events with delivery status, attempts and the
     * last error (the dashboard activity view).
     *
     * @param array{limit?:int,starting_after?:string} $params
     *
     * @return array<string,mixed>
     */
    public function events(string $id, array $params = []): array
    {
        return $this->http->request('GET', '/v1/ssf/streams/' . rawurlencode($id) . '/events', query: $params);
    }
}
