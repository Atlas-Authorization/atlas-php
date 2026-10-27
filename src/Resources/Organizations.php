<?php

declare(strict_types=1);

namespace Atlas\Resources;

use Atlas\Http;

/**
 * `/v1/organizations` — orgs plus their memberships, invitations, domains and
 * directory-group role grants (the nested sub-resources below).
 *
 * @phpstan-import-type AtlasDesign from Design
 * @phpstan-import-type AtlasDesignPatch from Design
 * @phpstan-import-type DesignSettings from Design
 * @phpstan-type EndUserPoolPolicy array{enabled:bool,requireApproval?:bool,allowHostedSignup?:bool}
 * @phpstan-type OrgPolicy array{requireMfa?:bool,ssoRequired?:bool,sessionIdleOverrideMs?:int,allowedSignInMethods?:list<string>,ipAllowlist?:list<string>,maxSessionAgeSeconds?:int,endUserPool?:EndUserPoolPolicy}
 * @phpstan-type OrgPolicyPatch array{requireMfa?:bool|null,ssoRequired?:bool|null,sessionIdleOverrideMs?:int|null,allowedSignInMethods?:list<string>|null,ipAllowlist?:list<string>|null,maxSessionAgeSeconds?:int|null,endUserPool?:EndUserPoolPolicy|null,entitlementPlan?:null,entitlementOverrides?:null}
 */
final class Organizations extends Resource
{
    public readonly OrganizationsWebhookEndpoints $webhookEndpoints;
    /** The organization policy keys the API knows about (camelCase, as sent). */
    public const POLICY_FIELDS = [
        'requireMfa', 'ssoRequired', 'sessionIdleOverrideMs', 'allowedSignInMethods',
        'ipAllowlist', 'maxSessionAgeSeconds', 'endUserPool', 'entitlementPlan', 'entitlementOverrides',
    ];

    public readonly OrganizationMemberships $memberships;
    public readonly OrganizationInvitations $invitations;
    public readonly OrganizationDomains $domains;
    public readonly OrganizationGroupRoles $groupRoles;
    public readonly OrganizationReaderDomain $readerDomain;
    public readonly OrganizationEndUsers $endUsers;
    public readonly OrganizationAuditStreams $auditStreams;
    public readonly OrganizationTeams $teams;
    public readonly OrganizationRoles $roles;

    public function __construct(Http $http)
    {
        parent::__construct($http);
        $this->webhookEndpoints = new OrganizationsWebhookEndpoints($http);
        $this->memberships = new OrganizationMemberships($http);
        $this->invitations = new OrganizationInvitations($http);
        $this->domains = new OrganizationDomains($http);
        $this->groupRoles = new OrganizationGroupRoles($http);
        $this->auditStreams = new OrganizationAuditStreams($http);
        $this->endUsers = new OrganizationEndUsers($http);
        $this->readerDomain = new OrganizationReaderDomain($http);
        $this->teams = new OrganizationTeams($http);
        $this->roles = new OrganizationRoles($http);
    }

    /**
     * Fetch an organization by slug (case-insensitive).
     *
     * @return array<string,mixed>
     */
    public function getBySlug(string $slug): array
    {
        return $this->http->request('GET', '/v1/organizations/by_slug/' . rawurlencode($slug));
    }

    /**
     * Whether a slug is valid and free: `{ slug, valid, available }`.
     *
     * @return array<string,mixed>
     */
    public function checkSlug(string $slug): array
    {
        return $this->http->request('GET', '/v1/organizations/check_slug', query: ['slug' => $slug]);
    }

    /**
     * @param array{limit?:int,starting_after?:string} $params
     *
     * @return array<string,mixed> a CursorPage of organizations
     */
    public function list(array $params = []): array
    {
        return $this->http->request('GET', '/v1/organizations', query: $params);
    }

    /** @return array<string,mixed> */
    public function get(string $id): array
    {
        return $this->http->request('GET', '/v1/organizations/' . rawurlencode($id));
    }

    /**
     * @param array{name:string,slug:string,created_by:string,max_allowed_memberships?:int} $body
     *
     * @return array<string,mixed>
     */
    public function create(array $body, ?string $idempotencyKey = null): array
    {
        return $this->http->request('POST', '/v1/organizations', body: $body, idempotencyKey: $idempotencyKey);
    }

    /**
     * @param array<string,mixed> $body
     *
     * @return array<string,mixed>
     */
    public function update(string $id, array $body): array
    {
        return $this->http->request('PATCH', '/v1/organizations/' . rawurlencode($id), body: $body);
    }

    /** @return array<string,mixed> a DeletedObject */
    public function delete(string $id): array
    {
        return $this->http->request('DELETE', '/v1/organizations/' . rawurlencode($id));
    }

    /**
     * `PUT /v1/organizations/:id/metadata` — replaces the named bags wholesale.
     *
     * @param array<string,mixed> $body
     *
     * @return array<string,mixed>
     */
    public function updateMetadata(string $id, array $body): array
    {
        return $this->http->request('PUT', '/v1/organizations/' . rawurlencode($id) . '/metadata', body: $body);
    }

    /**
     * `PATCH /v1/organizations/:id/policy` — the org security policy, as a
     * partial merge (camelCase keys; null clears an override):
     *
     * - `requireMfa`, `ssoRequired` (bool)
     * - `sessionIdleOverrideMs` (int)
     * - `allowedSignInMethods` (e.g. `password`, `passkey`, `oauth`, `oauth:google`, `saml`, `magic_link`)
     * - `ipAllowlist` (CIDR ranges allowed to sign in)
     * - `maxSessionAgeSeconds` (300 to 7776000)
     * - `endUserPool` (`enabled`, `requireApproval`, `allowHostedSignup`)
     * - `entitlementPlan`, `entitlementOverrides` — retired; only null (clear) is
     *   accepted. Use {@see EntitlementOverrides} instead.
     *
     * A key not listed here is passed through unchanged, so a newer server field
     * works without an SDK upgrade. {@see self::POLICY_FIELDS} lists the known keys.
     *
     *     $atlas->organizations->updatePolicy('org_1', ['requireMfa' => true, 'ipAllowlist' => ['203.0.113.0/24']]);
     *
     * @param OrgPolicyPatch $body
     *
     * @return array{object:string,organization_id:string,policy:OrgPolicy}
     */
    public function updatePolicy(string $id, array $body): array
    {
        return $this->http->request('PATCH', '/v1/organizations/' . rawurlencode($id) . '/policy', body: $body);
    }

    /**
     * `PUT /v1/organizations/:id/parent` — set (or clear, with null) the parent org.
     *
     * @param array{parent_organization_id:?string} $body
     *
     * @return array<string,mixed>
     */
    public function setParent(string $id, array $body): array
    {
        return $this->http->request('PUT', '/v1/organizations/' . rawurlencode($id) . '/parent', body: $body);
    }

    /** `GET /v1/organizations/:id/hierarchy` — ancestor chain + direct children. @return array<string,mixed> */
    public function hierarchy(string $id): array
    {
        return $this->http->request('GET', '/v1/organizations/' . rawurlencode($id) . '/hierarchy');
    }

    /** `GET /v1/organizations/:id/entitlements` — the org's active feature set. @return array<string,mixed> */
    public function entitlements(string $id): array
    {
        return $this->http->request('GET', '/v1/organizations/' . rawurlencode($id) . '/entitlements');
    }

    /**
     * `GET /v1/organizations/:id/settings` — the organization's settings values (validated against the
     * organization settings schema).
     *
     * @return array<string,mixed>
     */
    public function getSettings(string $id): array
    {
        return $this->http->request('GET', '/v1/organizations/' . rawurlencode($id) . '/settings');
    }

    /**
     * `PUT /v1/organizations/:id/settings` — replace the organization's settings values.
     *
     * @param array<string,mixed> $values
     *
     * @return array<string,mixed>
     */
    public function replaceSettings(string $id, array $values, ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'PUT',
            '/v1/organizations/' . rawurlencode($id) . '/settings',
            body: ['values' => $values === [] ? new \stdClass() : $values],
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * `PATCH /v1/organizations/:id/settings` — merge into the organization's settings values: a key set to
     * null is removed, other keys are set, untouched keys stay.
     *
     * @param array<string,mixed> $values
     *
     * @return array<string,mixed>
     */
    public function updateSettings(string $id, array $values, ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'PATCH',
            '/v1/organizations/' . rawurlencode($id) . '/settings',
            body: ['values' => $values === [] ? new \stdClass() : $values],
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * `GET /v1/organizations/:id/design` — the organization's design override and the result of layering
     * it over the instance design.
     *
     * @return DesignSettings
     */
    public function getDesign(string $id): array
    {
        return $this->http->request('GET', '/v1/organizations/' . rawurlencode($id) . '/design');
    }

    /**
     * `PUT /v1/organizations/:id/design` — replace the organization's design override. An empty design
     * removes it.
     *
     * @param AtlasDesign $design
     *
     * @return DesignSettings
     */
    public function replaceDesign(string $id, array $design): array
    {
        return $this->http->request(
            'PUT',
            '/v1/organizations/' . rawurlencode($id) . '/design',
            body: ['design' => $design === [] ? new \stdClass() : $design],
        );
    }

    /**
     * `PATCH /v1/organizations/:id/design` — change part of the organization's design override; null
     * removes a setting.
     *
     * @param AtlasDesignPatch $design
     *
     * @return DesignSettings
     */
    public function updateDesign(string $id, array $design): array
    {
        return $this->http->request(
            'PATCH',
            '/v1/organizations/' . rawurlencode($id) . '/design',
            body: ['design' => $design === [] ? new \stdClass() : $design],
        );
    }

    /**
     * `GET /v1/organizations/:id/branding` — the organization's hosted-page branding overlay. Unset fields
     * inherit the instance brand.
     *
     * @return array<string,mixed>
     */
    public function getBranding(string $id): array
    {
        return $this->http->request('GET', '/v1/organizations/' . rawurlencode($id) . '/branding');
    }

    /**
     * `PUT /v1/organizations/:id/branding` — set the organization's branding overlay (partial merge; null
     * clears a field back to the instance brand).
     *
     * @param array<string,mixed> $body
     *
     * @return array<string,mixed>
     */
    public function updateBranding(string $id, array $body): array
    {
        return $this->http->request('PUT', '/v1/organizations/' . rawurlencode($id) . '/branding', body: $body);
    }

    /**
     * `GET /v1/organizations/:id/connected_apps` - List the apps (OAuth clients) that hold an active grant
     * in this organization's context, one entry per grant.
     *
     * @param array{limit?:int,starting_after?:string} $params
     *
     * @return array<string,mixed>
     */
    public function listConnectedApps(string $id, array $params = []): array
    {
        return $this->http->request(
            'GET',
            '/v1/organizations/' . rawurlencode($id) . '/connected_apps',
            query: $params,
        );
    }

    /**
     * `DELETE /v1/organizations/:id/connected_apps/:grantId` - Revoke one connected app grant of the
     * organization: its tokens and any session minted from it stop working. Emits grant.revoked; audited
     * as organization.connected_app_revoked.
     *
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>
     */
    public function revokeConnectedApp(string $id, string $grantId, array $params = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'DELETE',
            '/v1/organizations/' . rawurlencode($id) . '/connected_apps/' . rawurlencode($grantId),
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * `GET /v1/organizations/:id/connections/:connectionId/secret` - The same read, scoped to an
     * organization: 404 unless the connection is owned by that organization. Needs
     * connections:secret_read; audited.
     *
     * @param array{version?:string} $params
     *
     * @return array<string,mixed>
     */
    public function getConnectionSecret(string $id, string $connectionId, array $params = []): array
    {
        return $this->http->request(
            'GET',
            '/v1/organizations/' . rawurlencode($id) . '/connections/' . rawurlencode($connectionId) . '/secret',
            query: $params,
        );
    }
}
