<?php

declare(strict_types=1);

namespace Atlas\Resources;

/** `/v1/sso_onboarding_profiles` + tickets — self-service SSO onboarding (Admin Portal). */
final class SsoOnboarding extends Resource
{
    /** @return array<string,mixed> a ListPage of profiles */
    public function list(): array
    {
        return $this->http->request('GET', '/v1/sso_onboarding_profiles');
    }

    /** @return array<string,mixed> */
    public function get(string $id): array
    {
        return $this->http->request('GET', '/v1/sso_onboarding_profiles/' . rawurlencode($id));
    }

    /**
     * @param array{name:string,allowed_connection_types?:list<string>,organization_id?:?string,company_name?:?string,allow_scim?:bool} $body
     *
     * @return array<string,mixed>
     */
    public function create(array $body, ?string $idempotencyKey = null): array
    {
        return $this->http->request('POST', '/v1/sso_onboarding_profiles', body: $body, idempotencyKey: $idempotencyKey);
    }

    /** @return array<string,mixed> */
    public function delete(string $id): array
    {
        return $this->http->request('DELETE', '/v1/sso_onboarding_profiles/' . rawurlencode($id));
    }

    /**
     * Issue a one-time ticket for a profile. The token is returned once, here.
     *
     * @param array{organization_id?:?string,expires_in_seconds?:int} $body
     *
     * @return array<string,mixed>
     */
    public function createTicket(string $profileId, array $body = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'POST',
            '/v1/sso_onboarding_profiles/' . rawurlencode($profileId) . '/tickets',
            body: $body,
            idempotencyKey: $idempotencyKey,
        );
    }

    /** Kill a still-live ticket. @return array<string,mixed> */
    public function revokeTicket(string $ticketId): array
    {
        return $this->http->request('POST', '/v1/sso_onboarding_tickets/' . rawurlencode($ticketId) . '/revoke');
    }
}
