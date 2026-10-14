<?php

declare(strict_types=1);

namespace Atlas\Resources;

/**
 * `/v1/organizations/:id/audit_streams` — per-organization audit streaming to a SIEM.
 */
final class OrganizationAuditStreams extends Resource
{
    /**
     * `GET /v1/organizations/:id/audit_streams` — the organization's audit stream destinations (never the
     * signing secret).
     *
     * @return array<string,mixed>
     */
    public function list(string $organizationId): array
    {
        return $this->http->request('GET', '/v1/organizations/' . rawurlencode($organizationId) . '/audit_streams');
    }

    /**
     * `POST /v1/organizations/:id/audit_streams` — register an HTTPS audit stream destination for the
     * organization.
     *
     * @param array<string,mixed> $body
     *
     * @return array<string,mixed>
     */
    public function create(string $organizationId, array $body, ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'POST',
            '/v1/organizations/' . rawurlencode($organizationId) . '/audit_streams',
            body: $body,
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * `DELETE /v1/organizations/:id/audit_streams/:streamId` — delete an audit stream destination.
     *
     * @return array<string,mixed>
     */
    public function delete(string $organizationId, string $streamId): array
    {
        return $this->http->request(
            'DELETE',
            '/v1/organizations/' . rawurlencode($organizationId) . '/audit_streams/' . rawurlencode($streamId),
        );
    }
}
