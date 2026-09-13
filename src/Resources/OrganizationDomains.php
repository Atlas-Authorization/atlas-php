<?php

declare(strict_types=1);

namespace Atlas\Resources;

/** `/v1/organizations/:id/domains` — verified-domain auto-join for an org. */
final class OrganizationDomains extends Resource
{
    /** @return array<string,mixed> */
    public function list(string $orgId): array
    {
        return $this->http->request('GET', '/v1/organizations/' . rawurlencode($orgId) . '/domains');
    }

    /**
     * @param array{domain:string,auto_join?:bool,default_role_id?:?string} $body
     *
     * @return array<string,mixed>
     */
    public function create(string $orgId, array $body, ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'POST',
            '/v1/organizations/' . rawurlencode($orgId) . '/domains',
            body: $body,
            idempotencyKey: $idempotencyKey,
        );
    }

    /** @return array<string,mixed> */
    public function verify(string $orgId, string $domainId): array
    {
        return $this->http->request(
            'POST',
            '/v1/organizations/' . rawurlencode($orgId) . '/domains/' . rawurlencode($domainId) . '/verify',
        );
    }

    /** @return array<string,mixed> */
    public function delete(string $orgId, string $domainId): array
    {
        return $this->http->request(
            'DELETE',
            '/v1/organizations/' . rawurlencode($orgId) . '/domains/' . rawurlencode($domainId),
        );
    }
}
