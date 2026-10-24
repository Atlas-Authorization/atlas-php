<?php

declare(strict_types=1);

namespace Atlas\Resources;

/**
 * `/v1/audit_exports` — stream the audit log to your own endpoint (signed webhook). The signing secret is shown once.
 */
final class AuditExports extends Resource
{
    /**
     * List exports (adds `mandatory_actions`).
     *
     * @return array<string,mixed>
     */
    public function list(): array
    {
        return $this->http->request('GET', '/v1/audit_exports');
    }

    /**
     * One export.
     *
     * @return array<string,mixed>
     */
    public function get(string $id): array
    {
        return $this->http->request('GET', '/v1/audit_exports/' . rawurlencode($id));
    }

    /**
     * Create an export.
     *
     * @return array<string,mixed>
     */
    public function create(array $body, ?string $idempotencyKey = null): array
    {
        return $this->http->request('POST', '/v1/audit_exports', body: $body, idempotencyKey: $idempotencyKey);
    }

    /**
     * Update an export.
     *
     * @return array<string,mixed>
     */
    public function update(string $id, array $body): array
    {
        return $this->http->request('PATCH', '/v1/audit_exports/' . rawurlencode($id), body: $body);
    }

    /**
     * Delete an export.
     *
     * @return array<string,mixed>
     */
    public function delete(string $id): array
    {
        return $this->http->request('DELETE', '/v1/audit_exports/' . rawurlencode($id));
    }

    /**
     * Send a test entry.
     *
     * @return array<string,mixed>
     */
    public function test(string $id): array
    {
        return $this->http->request('POST', '/v1/audit_exports/' . rawurlencode($id) . '/test');
    }

    /**
     * Rotate the signing secret (shown once).
     *
     * @return array<string,mixed>
     */
    public function rotateSecret(string $id): array
    {
        return $this->http->request('POST', '/v1/audit_exports/' . rawurlencode($id) . '/rotate_secret');
    }
}
