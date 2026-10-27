<?php

declare(strict_types=1);

namespace Atlas\Resources;

/**
 * `/v1` - 7 operations.
 */
final class AuditEventSchemas extends Resource
{
    /**
     * `GET /v1/audit_event_schemas` - List the registered audit event schemas, by action then version
     * (newest first).
     *
     * @param array{limit?:int,starting_after?:string} $params
     *
     * @return array<string,mixed>
     */
    public function list(array $params = []): array
    {
        return $this->http->request('GET', '/v1/audit_event_schemas', query: $params);
    }

    /**
     * `POST /v1/audit_event_schemas` - Register a schema for an action: the target types and actor types
     * it allows and a JSON Schema its metadata must satisfy. `version` defaults to the next number for
     * that action; a version is immutable (registering it again is a 409) - change a schema by registering
     * a new version. From then on events with this action are validated on write.
     *
     * @param array{action:string,version?:int,description?:string,target_types?:list<string>,actor_types?:list<string>,metadata_schema?:array<string,mixed>} $body
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>
     */
    public function register(array $body, array $params = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'POST',
            '/v1/audit_event_schemas',
            body: $body,
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * `GET /v1/audit_event_schemas/:id` - One audit event schema.
     *
     * @return array<string,mixed>
     */
    public function get(string $id): array
    {
        return $this->http->request('GET', '/v1/audit_event_schemas/' . rawurlencode($id));
    }

    /**
     * `PATCH /v1/audit_event_schemas/:id` - Change a schema version's description or status. A deprecated
     * version is no longer applied by default but still validates events that name it with
     * `schema_version`.
     *
     * @param array{description?:string|null,status?:'active'|'deprecated'} $body
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>
     */
    public function update(string $id, array $body, array $params = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'PATCH',
            '/v1/audit_event_schemas/' . rawurlencode($id),
            body: $body,
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * `DELETE /v1/audit_event_schemas/:id` - Remove a schema version. Events already recorded keep their
     * `schema_version` number.
     *
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>
     */
    public function delete(string $id, array $params = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'DELETE',
            '/v1/audit_event_schemas/' . rawurlencode($id),
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * `POST /v1/audit_events/:id/redact` - Blank named fields of one audit event (replaced by [redacted])
     * without deleting it: `actor_id`, `target_id`, `target_name` or `metadata.<dotted.path>`. A `reason`
     * is required. The redaction is recorded in its own log (GET /v1/audit_events/:id/redactions) and in
     * Atlas's audit log as `audit_event.redacted` - the fields and the reason, never the removed values.
     *
     * @param array{fields:list<string>,reason:string} $body
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>
     */
    public function redact(string $id, array $body, array $params = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'POST',
            '/v1/audit_events/' . rawurlencode($id) . '/redact',
            body: $body,
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * `GET /v1/audit_events/:id/redactions` - An audit event's redaction history: which fields were
     * blanked, why, and by which key.
     *
     * @param array{limit?:int,starting_after?:string} $params
     *
     * @return array<string,mixed>
     */
    public function redactions(string $id, array $params = []): array
    {
        return $this->http->request('GET', '/v1/audit_events/' . rawurlencode($id) . '/redactions', query: $params);
    }
}
