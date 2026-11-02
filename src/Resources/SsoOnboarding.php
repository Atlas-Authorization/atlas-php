<?php

declare(strict_types=1);

namespace Atlas\Resources;

/** `/v1/sso_onboarding_profiles` + tickets — self-service SSO onboarding (Admin Portal). */
final class SsoOnboarding extends Resource
{
    /**
     * With `$name`, only the profiles with exactly that name ("find or create").
     *
     * @return array<string,mixed> a ListPage of profiles
     */
    public function list(?string $name = null): array
    {
        return $this->http->request('GET', '/v1/sso_onboarding_profiles', query: ['name' => $name]);
    }

    /** @return array<string,mixed> */
    public function get(string $id): array
    {
        return $this->http->request('GET', '/v1/sso_onboarding_profiles/' . rawurlencode($id));
    }

    /**
     * `return_url` (https, on one of your allowed origins) is where "Finish" on
     * the hosted setup page sends the admin.
     *
     * @param array{name:string,allowed_connection_types?:list<string>,organization_id?:?string,company_name?:?string,allow_scim?:bool,intents?:list<string>,return_url?:?string} $body
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
     * `return_url` overrides the profile's for this ticket; the answer carries
     * `effective_return_url` (the ticket's, else the profile's). The lifetime is
     * clamped to 5 minutes – 30 days (default 3 days).
     *
     * @param array{organization_id?:?string,expires_in_seconds?:int,return_url?:?string} $body
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
