<?php

declare(strict_types=1);

namespace Atlas;

/**
 * Atlas-specific claims on the ID tokens and userinfo of an OIDC relying party: the organization a sign-in was made in (`org_id`,
 * `org_slug`, `org_role`, `org_permissions`), every membership (`orgs`, with the `organizations` scope) and the enterprise SSO
 * connection (`sso_connection_id`, `sso_organization_id`, `sso_connection_type`, `sso_tenant_id`, `sso_issuer`, `sso_domain`). Pass the verified claims of the token.
 *
 *     $org = OidcClaims::organization($claims);        // ['id','slug','role','permissions'] or null
 *     $all = OidcClaims::organizations($claims);       // [['id','slug','role'], …] or null
 *     $sso = OidcClaims::sso($claims);                 // ['connection_id','tenant_id','issuer','domain'] or null
 *     $url = OidcClaims::authorizeUrlWithOrganization($authorizeUrl, 'acme');
 */
final class OidcClaims
{
    /** The scope that adds the `orgs` membership list to the ID token and userinfo. */
    public const SCOPE_ORGANIZATIONS = 'organizations';
    /** The authorize parameter that pins the organization (id or slug) a sign-in is made in. */
    public const ORGANIZATION_PARAM = 'organization';

    /**
     * The organization the sign-in was made in, or null when none was involved.
     *
     * @param array<string,mixed> $claims
     *
     * @return array{id:string,slug:string,role:string,permissions:list<string>}|null
     */
    public static function organization(array $claims): ?array
    {
        if (!isset($claims['org_id']) || !is_string($claims['org_id'])) {
            return null;
        }
        $permissions = is_array($claims['org_permissions'] ?? null) ? array_values(array_filter($claims['org_permissions'], 'is_string')) : [];

        return [
            'id' => $claims['org_id'],
            'slug' => is_string($claims['org_slug'] ?? null) ? $claims['org_slug'] : '',
            'role' => is_string($claims['org_role'] ?? null) ? $claims['org_role'] : '',
            'permissions' => $permissions,
        ];
    }

    /**
     * Every membership (the `orgs` claim of the `organizations` scope), or null when the scope was not requested.
     *
     * @param array<string,mixed> $claims
     *
     * @return list<array{id:string,slug:string,role:string}>|null
     */
    public static function organizations(array $claims): ?array
    {
        if (!is_array($claims['orgs'] ?? null)) {
            return null;
        }
        $out = [];
        foreach ($claims['orgs'] as $org) {
            if (is_array($org) && isset($org['id']) && is_string($org['id'])) {
                $out[] = [
                    'id' => $org['id'],
                    'slug' => is_string($org['slug'] ?? null) ? $org['slug'] : '',
                    'role' => is_string($org['role'] ?? null) ? $org['role'] : '',
                ];
            }
        }

        return $out;
    }

    /**
     * The enterprise SSO connection the user signed in through, or null. `organization_id` is the Atlas organization that owns
     * the connection (null for an instance-wide one), independent of `org_id`: scope a per-organization "require SSO" rule on it,
     * never on "signed in via SSO". `type` is `saml` or `oidc` (null when absent). Works on ID-token, userinfo and session claims.
     *
     * @param array<string,mixed> $claims
     *
     * @return array{connection_id:string,organization_id:string|null,type:'saml'|'oidc'|null,tenant_id:string|null,issuer:string|null,domain:string|null}|null
     */
    public static function sso(array $claims): ?array
    {
        if (!isset($claims['sso_connection_id']) || !is_string($claims['sso_connection_id'])) {
            return null;
        }
        $str = static fn (string $k): ?string => is_string($claims[$k] ?? null) ? $claims[$k] : null;

        return [
            'connection_id' => $claims['sso_connection_id'],
            'organization_id' => $str('sso_organization_id'),
            'type' => in_array($claims['sso_connection_type'] ?? null, ['saml', 'oidc'], true) ? $claims['sso_connection_type'] : null,
            'tenant_id' => $str('sso_tenant_id'),
            'issuer' => $str('sso_issuer'),
            'domain' => $str('sso_domain'),
        ];
    }

    /**
     * Add (or replace) the `organization` authorize parameter: the sign-in must be made in that organization (a user who is not a
     * member gets `access_denied`) and the token's `org_*` claims are that organization's.
     */
    public static function authorizeUrlWithOrganization(string $authorizeUrl, string $organization): string
    {
        $parts = parse_url($authorizeUrl);
        $query = [];
        if (is_array($parts) && isset($parts['query'])) {
            parse_str($parts['query'], $query);
        }
        $query[self::ORGANIZATION_PARAM] = $organization;
        $base = explode('?', explode('#', $authorizeUrl, 2)[0], 2)[0];

        return $base . '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    }

    private function __construct()
    {
    }
}
