<?php

declare(strict_types=1);

namespace Atlas\Resources;

/**
 * `/v1/platform/atlas_id_apps` — Sign in with Atlas apps (OpenID Connect clients) for your account.
 *
 *     $app = $atlas->platform->atlasIdApps->create([
 *         'name' => 'Acme',
 *         'redirect_uris' => ['https://acme.com/callback'],
 *         'backchannel_logout_uri' => 'https://acme.com/logout/backchannel',
 *         'branding' => ['theme' => 'dark', 'accent_color' => '#7c3aed'],
 *     ]);
 *
 * @phpstan-import-type AtlasDesign from Design
 * @phpstan-import-type DesignPreview from Design
 * @phpstan-type AtlasIdAppCopy array{sign_in_title?:string|null,sign_in_subtitle?:string|null,sign_up_title?:string|null,sign_up_subtitle?:string|null,consent_message?:string|null,support_url?:string|null,support_label?:string|null}
 * @phpstan-type AtlasIdAppBrandingInput array{theme?:'auto'|'light'|'dark'|null,accent_color?:string|null,background_color?:string|null,privacy_policy_url?:string|null,terms_of_service_url?:string|null,copy?:AtlasIdAppCopy|null,design?:AtlasDesign|null}
 * @phpstan-type AtlasIdAppInput array{name?:string,redirect_uris?:list<string>,post_logout_redirect_uris?:list<string>,backchannel_logout_uri?:string|null,backchannel_logout_session_required?:bool,frontchannel_logout_uri?:string|null,frontchannel_logout_session_required?:bool,logo_url?:string|null,homepage_url?:string|null,allowed_scopes?:list<'openid'|'profile'|'email'|'offline_access'>,branding?:AtlasIdAppBrandingInput,type?:'confidential'|'public',webhook_url?:string|null}
 * @phpstan-type AtlasIdAppBranding array{theme:'auto'|'light'|'dark',accent_color:string|null,background_color:string|null,privacy_policy_url:string|null,terms_of_service_url:string|null,copy:AtlasIdAppCopy,design?:AtlasDesign|null,resolved_design?:array<string,mixed>}
 * @phpstan-type AtlasIdApp array{object:'atlas_id_app',id:string,client_id:string,name:string,logo_url?:string|null,homepage_url?:string|null,branding?:AtlasIdAppBranding,redirect_uris:list<string>,post_logout_redirect_uris:list<string>,backchannel_logout_uri:string|null,backchannel_logout_session_required:bool,frontchannel_logout_uri:string|null,frontchannel_logout_session_required:bool,allowed_scopes:list<string>,type:'confidential'|'public',secret_prefix?:string|null,managed_by_instance_id?:string|null,created_at:int,updated_at:int,user_count?:int,client_secret?:string,webhook_url?:string|null,webhook_events?:list<string>,webhook_active?:bool|null,webhook_secret?:string}
 */
final class PlatformAtlasIdApps extends Resource
{
    /**
     * `GET /v1/platform/atlas_id_apps` — your Sign in with Atlas apps, with the
     * issuer and discovery URL. Never a secret.
     *
     * @return array{object:string,data:list<AtlasIdApp>}
     */
    public function list(): array
    {
        return $this->http->request('GET', '/v1/platform/atlas_id_apps');
    }

    /**
     * `POST /v1/platform/atlas_id_apps` — register a Sign in with Atlas app.
     *
     * - `redirect_uris`: exact-match https URLs (`http://localhost` allowed).
     * - `type`: `confidential` (default; its `client_secret` is returned exactly
     *   once) or `public` (PKCE, no secret).
     * - `allowed_scopes`: `openid` (always), `profile`, `email`, `offline_access`.
     * - `post_logout_redirect_uris`: where `/oauth2/logout` may return to.
     * - `backchannel_logout_uri` (+ `_session_required`): Atlas POSTs a
     *   `logout_token` here on sign-out; verify it with
     *   {@see \Atlas\Verify\BackchannelLogout}.
     * - `frontchannel_logout_uri` (+ `_session_required`).
     * - `branding`: `theme`, `accent_color`, `background_color`,
     *   `privacy_policy_url`, `terms_of_service_url`, `copy` (page text; `{app}`
     *   becomes the app name) and `design` (a full design, see {@see Design}).
     *
     * @param AtlasIdAppInput $body
     *
     * @return AtlasIdApp
     */
    public function create(array $body, ?string $idempotencyKey = null): array
    {
        return $this->http->request('POST', '/v1/platform/atlas_id_apps', body: $body, idempotencyKey: $idempotencyKey);
    }

    /**
     * `GET /v1/platform/atlas_id_apps/:id` — one Sign in with Atlas app, including `user_count`.
     *
     * @return AtlasIdApp
     */
    public function get(string $id): array
    {
        return $this->http->request('GET', '/v1/platform/atlas_id_apps/' . rawurlencode($id));
    }

    /**
     * `PATCH /v1/platform/atlas_id_apps/:id` — update a Sign in with Atlas app.
     * `branding` merges per field (null clears a field); `[]` for
     * `post_logout_redirect_uris` clears them; an empty or null logout URI
     * clears it. The app type cannot change.
     *
     * @param AtlasIdAppInput $body
     *
     * @return AtlasIdApp
     */
    public function update(string $id, array $body): array
    {
        return $this->http->request('PATCH', '/v1/platform/atlas_id_apps/' . rawurlencode($id), body: $body);
    }

    /**
     * `DELETE /v1/platform/atlas_id_apps/:id` — delete a Sign in with Atlas app;
     * its client_id stops working immediately.
     *
     * @return array{object:string,id:string,deleted:bool}
     */
    public function delete(string $id): array
    {
        return $this->http->request('DELETE', '/v1/platform/atlas_id_apps/' . rawurlencode($id));
    }

    /**
     * `POST /v1/platform/atlas_id_apps/:id/rotate_secret` — rotate a confidential
     * app's client_secret. The new secret is returned once; the old one keeps
     * working for 24 hours.
     *
     * @return AtlasIdApp
     */
    public function rotateSecret(string $id): array
    {
        return $this->http->request('POST', '/v1/platform/atlas_id_apps/' . rawurlencode($id) . '/rotate_secret');
    }

    /**
     * `GET /v1/platform/atlas_id_apps/:id/backchannel_deliveries` — the app's back-channel logout
     * delivery log, newest first (`limit`, `starting_after`).
     *
     * @param array{limit?:int,starting_after?:string} $params
     *
     * @return array{object:string,data:list<array<string,mixed>>,has_more:bool,next_cursor:string|null}
     */
    public function listBackchannelDeliveries(string $id, array $params = []): array
    {
        return $this->http->request('GET', '/v1/platform/atlas_id_apps/' . rawurlencode($id) . '/backchannel_deliveries', query: $params);
    }

    /**
     * `POST /v1/platform/atlas_id_apps/:id/backchannel_logout/test` — send a test back-channel
     * logout to the app now.
     *
     * @return array<string,mixed>
     */
    public function testBackchannelLogout(string $id): array
    {
        return $this->http->request('POST', '/v1/platform/atlas_id_apps/' . rawurlencode($id) . '/backchannel_logout/test');
    }

    /**
     * `GET /v1/platform/atlas_id_apps/:id/preflight` — check one app's setup (`ok`, `user_count`,
     * `findings` in the instance preflight's shape).
     *
     * @return array<string,mixed>
     */
    public function preflight(string $id): array
    {
        return $this->http->request('GET', '/v1/platform/atlas_id_apps/' . rawurlencode($id) . '/preflight');
    }

    /**
     * `GET /v1/platform/atlas_id_apps/:id/users?email=` — privacy requests: is this person one of
     * the app's users? Exact match on a verified email; anyone else gets the same empty list.
     *
     * @return array{object:string,data:list<array{object:string,atlas_id_user:bool,sub:string,grant_id:string,scopes:list<string>,granted_at:int}>}
     */
    public function findUsers(string $id, string $email): array
    {
        return $this->http->request('GET', '/v1/platform/atlas_id_apps/' . rawurlencode($id) . '/users', query: ['email' => $email]);
    }

    /**
     * `DELETE /v1/platform/atlas_id_apps/:id/grants/:grantId` — revoke one user's grant; the next
     * sign-in shows the consent screen again.
     *
     * @return array{object:string,id:string,sub:string,revoked:bool,tokens_revoked?:int,backchannel_logouts?:int}
     */
    public function revokeGrant(string $id, string $grantId): array
    {
        return $this->http->request('DELETE', '/v1/platform/atlas_id_apps/' . rawurlencode($id) . '/grants/' . rawurlencode($grantId));
    }

    /**
     * `POST /v1/platform/atlas_id_apps/:id/move_origin` — move the app's URLs from one origin to
     * another (`from`, `to`, `dry_run`).
     *
     * @param array{from:string,to:string,dry_run?:bool} $body
     *
     * @return array<string,mixed> the origin_move diff, with `app`
     */
    public function moveOrigin(string $id, array $body): array
    {
        return $this->http->request('POST', '/v1/platform/atlas_id_apps/' . rawurlencode($id) . '/move_origin', body: $body);
    }

    /**
     * `POST /v1/platform/atlas_id_apps/:id/webhook/rotate_secret` — rotate the app's webhook
     * signing secret (`webhook_secret`, returned once).
     *
     * @return AtlasIdApp
     */
    public function rotateWebhookSecret(string $id): array
    {
        return $this->http->request('POST', '/v1/platform/atlas_id_apps/' . rawurlencode($id) . '/webhook/rotate_secret');
    }

    /**
     * `POST /v1/platform/atlas_id_apps/design/preview` — preview a Sign in with
     * Atlas app design without saving it.
     *
     * @param AtlasDesign $design
     *
     * @return DesignPreview
     */
    public function previewDesign(array $design): array
    {
        return $this->http->request(
            'POST',
            '/v1/platform/atlas_id_apps/design/preview',
            body: ['design' => $design === [] ? new \stdClass() : $design],
        );
    }
}
