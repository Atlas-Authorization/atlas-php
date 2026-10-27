<?php

declare(strict_types=1);

namespace Atlas\Resources;

/**
 * `/v1/listen/sessions` - 2 operations.
 */
final class Listen extends Resource
{
    /**
     * `POST /v1/listen/sessions` - Start a local webhook forwarding session (what `atlas listen` uses).
     * Returns a session id, a signing secret for that session only (it is unrelated to any registered
     * endpoint's secret) and the stream URL. The session needs no storage: it expires at `expires_at`
     * (default 1 hour, `ttl_seconds` 60 to 43200).
     *
     * @param array{events?:list<string>,ttl_seconds?:int} $body
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>
     */
    public function createSession(array $body, array $params = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'POST',
            '/v1/listen/sessions',
            body: $body,
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * `GET /v1/listen/sessions/:id/stream` - Server-Sent Events stream of the instance's new events for a
     * listen session. Each `webhook` event carries `{id, type, headers, body}`: the headers (`atlas-id`,
     * `atlas-timestamp`, `atlas-signature`) and the exact JSON body of a would-be delivery, signed with
     * the session secret. Starts at "now" unless resumed with `Last-Event-ID` or `after`. Query: `events`
     * (comma-separated types or patterns), `max_events` and `max_wait_seconds` (end the stream early, for
     * scripts and CI). A `: keep-alive` comment is sent every 15 seconds; an `expired` event ends the
     * stream at the session expiry. 404 for an unknown session id, 400 once expired.
     *
     * @param array{events?:string,after?:string,max_events?:int,poll_ms?:int,max_wait_seconds?:int} $params
     *
     * @return string the raw event-stream text
     */
    public function streamSession(string $id, array $params = []): string
    {
        return (string) $this->http->request(
            'GET',
            '/v1/listen/sessions/' . rawurlencode($id) . '/stream',
            query: $params,
            raw: true,
        );
    }
}
