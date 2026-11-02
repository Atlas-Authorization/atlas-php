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

    /**
     * Check now. No body is the DNS TXT check; `['method' => 'well_known']` fetches
     * the file; `['method' => 'email', 'code' => '123456']` takes the emailed code.
     * Domains carry `verification_methods` and `verification_method`.
     *
     * @param array{method?:'dns_txt'|'well_known'|'email',code?:string} $body
     *
     * @return array<string,mixed>
     */
    public function verify(string $orgId, string $domainId, array $body = []): array
    {
        return $this->http->request(
            'POST',
            '/v1/organizations/' . rawurlencode($orgId) . '/domains/' . rawurlencode($domainId) . '/verify',
            body: $body === [] ? null : $body,
        );
    }

    /**
     * Email a 6-digit code to an administrative mailbox at the domain (default
     * `admin`), through the instance's own email provider (422
     * EMAIL_DELIVERY_NOT_CONFIGURED without one). The answer carries `sent_to`.
     *
     * @param 'admin'|'administrator'|'hostmaster'|'postmaster'|'webmaster'|null $address
     *
     * @return array<string,mixed>
     */
    public function sendVerificationEmail(string $orgId, string $domainId, ?string $address = null): array
    {
        return $this->http->request(
            'POST',
            '/v1/organizations/' . rawurlencode($orgId) . '/domains/' . rawurlencode($domainId) . '/verification_email',
            body: $address === null ? [] : ['address' => $address],
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
