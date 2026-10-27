<?php

declare(strict_types=1);

namespace Atlas\Resources;

/**
 * `/v1/oauth_clients/:id/app_settings` - 2 operations.
 */
final class OAuthClientsAppSettings extends Resource
{
    /**
     * `GET /v1/oauth_clients/:id/app_settings` - The per-application settings of an OAuth client: allowed
     * sign-in methods, hosted-page branding, the user-claim allow-list for ID tokens and userinfo, and the
     * traits a session from this app may write. Null = no restriction.
     *
     * @return array<string,mixed>
     */
    public function get(string $id): array
    {
        return $this->http->request('GET', '/v1/oauth_clients/' . rawurlencode($id) . '/app_settings');
    }

    /**
     * `PATCH /v1/oauth_clients/:id/app_settings` - Change the per-application settings. Each field sent
     * replaces the stored one and null clears it; fields left out keep their value. Unknown fields and
     * malformed values are refused.
     *
     * @param array{allowed_sign_in_methods?:list<string>|null,branding?:array{heading?:string|null,subheading?:string|null,logo_url?:string|null,primary_color?:string|null}|null,claims_allowlist?:list<string>|null,writable_traits?:list<string>|null,risk?:array{enabled?:bool|null,mode?:mixed,step_up_threshold?:mixed,on_high_risk_no_factor?:mixed,level_actions?:array<string,mixed>|null,mid_session?:array<string,mixed>|null}|null} $body
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>
     */
    public function update(string $id, array $body, array $params = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'PATCH',
            '/v1/oauth_clients/' . rawurlencode($id) . '/app_settings',
            body: $body,
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }
}
