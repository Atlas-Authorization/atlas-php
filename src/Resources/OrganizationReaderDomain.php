<?php

declare(strict_types=1);

namespace Atlas\Resources;

/**
 * `/v1/organizations/:orgId/reader_domain` — hosts whose sign-ups join the reader pool.
 */
final class OrganizationReaderDomain extends Resource
{
    /**
     * `GET /v1/organizations/:orgId/reader_domain` — the organization's reader domain(s) with DNS and
     * certificate status.
     *
     * @return array<string,mixed>
     */
    public function list(string $organizationId): array
    {
        return $this->http->request('GET', '/v1/organizations/' . rawurlencode($organizationId) . '/reader_domain');
    }

    /**
     * `POST /v1/organizations/:orgId/reader_domain` — bind a host to the organization's reader pool.
     * Returns the CNAME to publish.
     *
     * @return array<string,mixed>
     */
    public function create(string $organizationId, string $host, ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'POST',
            '/v1/organizations/' . rawurlencode($organizationId) . '/reader_domain',
            body: ['host' => $host],
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * `DELETE /v1/organizations/:orgId/reader_domain` — remove the organization's reader domain binding.
     *
     * @return array<string,mixed>
     */
    public function delete(string $organizationId): array
    {
        return $this->http->request('DELETE', '/v1/organizations/' . rawurlencode($organizationId) . '/reader_domain');
    }
}
