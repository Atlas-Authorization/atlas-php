<?php

declare(strict_types=1);

namespace Atlas\Resources;

/**
 * `/v1/audit_events` — your own custom audit events, streamed to each organization's SIEM.
 */
final class AuditEvents extends Resource
{
    /**
     * `POST /v1/audit_events` — write one of your own audit events (actor, action, target,
     * organization_id, occurred_at, metadata).
     *
     * @param array<string,mixed> $body
     *
     * @return array<string,mixed>
     */
    public function create(array $body): array
    {
        return $this->http->request('POST', '/v1/audit_events', body: $body);
    }

    /**
     * `POST /v1/audit_events/batch` — write up to 100 audit events atomically.
     *
     * @param list<mixed> $events
     *
     * @return array<string,mixed>
     */
    public function createBatch(array $events): array
    {
        return $this->http->request('POST', '/v1/audit_events/batch', body: ['events' => $events]);
    }

    /**
     * `GET /v1/audit_events` — query your audit events, newest first. Cursor-paginated.
     *
     * @param array<string,mixed> $params query parameters
     *
     * @return array<string,mixed>
     */
    public function list(array $params = []): array
    {
        return $this->http->request('GET', '/v1/audit_events', query: $params);
    }

    /**
     * `GET /v1/audit_events/export` — export every matching audit event as one document (format: "jsonl"
     * or "csv"). Returns the raw text.
     *
     * @param array<string,mixed> $params query parameters
     *
     * @return string the raw response text
     */
    public function export(array $params = []): string
    {
        return $this->http->request('GET', '/v1/audit_events/export', query: $params, raw: true);
    }

    /**
     * `DELETE /v1/audit_events` — bulk-delete audit events (development instances only), optionally
     * narrowed by organization_id and action.
     *
     * @param array<string,mixed> $params query parameters
     *
     * @return array<string,mixed>
     */
    public function deleteAll(array $params = []): array
    {
        return $this->http->request('DELETE', '/v1/audit_events', query: $params);
    }
}
