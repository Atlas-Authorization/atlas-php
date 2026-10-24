<?php

declare(strict_types=1);

namespace Atlas\Resources;

/**
 * `/v1/instance` — instance configuration. The instance is the tenant's
 * environment (its publishable key, frontend API host, allowed origins and auth
 * config). The signing private key is never returned. JWT templates live under
 * their own {@see JwtTemplates} resource, not here.
 *
 * @phpstan-type PreflightFinding array{severity:'error'|'warning'|'info',code:string,message:string,fix:string,subject?:array<string,string>}
 * @phpstan-type SdkAdvisory array{object:string,package:string,affected:string,fixed_in:string,reason:string,severity:'critical'|'high'|'medium'|'low',summary:string,published_at:string}
 * @phpstan-type SdkVersionSeen array{package:string,version:string,last_seen_at:int,advisories:list<SdkAdvisory>}
 * @phpstan-type PreflightReport array{object:string,ok:bool,checked_at:int,findings:list<PreflightFinding>,sdk_versions_seen?:list<SdkVersionSeen>}
 * @phpstan-type CookieSettingsLinkInput array{label:string,preset?:string|null,attributes?:array<string,string>}
 * @phpstan-type CookieSettingsLink array{label:string,preset:string|null,attributes:array<string,string>,rendered_attributes:array<string,string>}
 * @phpstan-type DeclaredCookie array{name:string|null,pattern:string|null,store:'cookie'|'localStorage'|'sessionStorage'|'indexedDB',domain:string,lifetime_seconds:int|null,lifetime:string,http_only:bool,category:string,purpose:string,written_when:string,set_by:string}
 * @phpstan-type CookieConsent array{object:string,enabled:bool,script_url?:string,script_id?:string|null,site_id?:string|null,blocking_mode?:string|null,extra_origins?:list<string>,settings_link?:CookieSettingsLink|null,culture?:'page'|'browser',declared_cookies?:list<DeclaredCookie>}
 * @phpstan-type CookieConsentInput array{script_url:string,script_id?:string|null,site_id?:string|null,blocking_mode?:string|null,extra_origins?:list<string>,settings_link?:CookieSettingsLinkInput|null,culture?:'page'|'browser'|null}
 * @phpstan-type OriginMoveInput array{from:string,to:string,dry_run?:bool}
 * @phpstan-type OriginMoveChange array{object:string,id:string,field:string,before:string|list<string>|null,after:string|list<string>|null}
 * @phpstan-type OriginMove array{object:'origin_move',from:string,to:string,dry_run:bool,applied:bool,changes:list<OriginMoveChange>,problems:list<array<string,string>>,app?:array<string,mixed>}
 * @phpstan-type InstanceConnection array{object:'connection',kind:string,check:string,ok:bool,severity:'error'|'warning'|'info',subject:array<string,string>,fix_hint:string|null,last_checked_at:int,details:array<string,mixed>}
 * @phpstan-type InstanceConnections array{object:'instance_connections',ok:bool,checked_at:int,data:list<InstanceConnection>}
 * @phpstan-type ChatlyIntegration array{object:'chatly_integration',enabled:bool,channel?:string,template?:string,audience?:string,issuer?:string,jwks_url?:string,token_ttl_seconds?:int,identity_verification?:array<string,string>}
 * @phpstan-type ForumulateIntegration array{object:'forumulate_integration',enabled:bool,org_id?:string,spaces?:list<array{id:string,redeem_url:string}>,audience?:string,issuer?:string,jwks_url?:string,token_ttl_seconds?:int,reader_auth_provider?:array<string,mixed>}
 * @phpstan-type ForumulateIntegrationInput array{org_id:string,spaces:list<string|array{id:string,redeem_url?:string|null}>,audience?:string|null}
 */
final class Instance extends Resource
{
    /** Cookie settings link preset: Cookiemunch's trigger. */
    public const COOKIE_SETTINGS_LINK_PRESET_COOKIEMUNCH = 'cookiemunch';
    /** Cookie settings link preset: Cookiebot's trigger. */
    public const COOKIE_SETTINGS_LINK_PRESET_COOKIEBOT = 'cookiebot';
    /** Cookie settings link preset: OneTrust's trigger. */
    public const COOKIE_SETTINGS_LINK_PRESET_ONETRUST = 'onetrust';
    /** Cookie settings link preset: no built-in trigger; your `attributes` wire the link up. */
    public const COOKIE_SETTINGS_LINK_PRESET_CUSTOM = 'custom';

    /** The cookie settings link presets the API knows about (a response may carry a newer one). */
    public const COOKIE_SETTINGS_LINK_PRESETS = ['cookiemunch', 'cookiebot', 'onetrust', 'custom'];

    /**
     * The instance, including `issuer`, `discovery_url` and `jwks_uri` (they change when a custom
     * domain goes active, so read them rather than hard-coding them) and
     * `allowed_origin_integrations` (origin => `redirect` | `embedded`).
     *
     * @return array<string,mixed> the Instance object
     */
    public function get(): array
    {
        return $this->http->request('GET', '/v1/instance');
    }

    /**
     * `PATCH /v1/instance` echoes only the mutable fields, not a full Instance.
     *
     * An `allowed_origins` entry is an origin string, or `['origin' => …, 'integration' => …]` where
     * `integration` is `redirect` (hosted-redirect OIDC), `embedded` (the sign-in components run on the
     * origin) or `auto` / null (clear the hint). A plain string keeps the origin's current hint.
     * `auth_config` also takes `strictRequestBodies`, `dangerouslyReportSkippedEmailsAsVerified` and
     * `branding.audiences`.
     *
     * @param array{allowed_origins?:list<string|array{origin:string,integration?:'redirect'|'embedded'|'auto'|null}>,auth_config?:array<string,mixed>} $body
     *
     * @return array<string,mixed>
     */
    public function update(array $body): array
    {
        return $this->http->request('PATCH', '/v1/instance', body: $body);
    }

    /**
     * `GET /v1/instance/preflight` — check your setup in one call. Read-only.
     *
     * `ok` is true when no finding is an error. Each finding has a `severity`
     * (`error`, `warning` or `info`), a stable `code`, a plain `message`, the
     * `fix`, and — when it is about one thing — a `subject` naming it (e.g.
     * `['provider' => 'google']`).
     *
     *     $report = $atlas->instance->preflight();
     *     foreach ($report['findings'] as $f) { echo "{$f['severity']}: {$f['message']} → {$f['fix']}\n"; }
     *
     * `$params`: `min_severity` (hide findings below it), `include_suppressed` (also list `suppressed[]`). The report adds
     * `min_severity`, `suppressed[]`, `integrations[]`; each finding has a `group`.
     *
     * @param array{min_severity?:string,include_suppressed?:bool} $params
     *
     * @return PreflightReport
     */
    public function preflight(array $params = []): array
    {
        return $this->http->request('GET', '/v1/instance/preflight', query: $params);
    }

    /**
     * `POST /v1/instance/move_origin` — move every URL that points at one origin to another (a
     * domain move), across the instance, its OAuth clients, webhooks, redirect URLs and Sign in
     * with Atlas apps. `dry_run: true` writes nothing; a repeated run returns no changes.
     *
     * @param OriginMoveInput $body
     *
     * @return OriginMove
     */
    public function moveOrigin(array $body): array
    {
        return $this->http->request('POST', '/v1/instance/move_origin', body: $body);
    }

    /**
     * `GET /v1/instance/connections` — the instance's links to other platforms and their health.
     *
     * @return InstanceConnections
     */
    public function connections(): array
    {
        return $this->http->request('GET', '/v1/instance/connections');
    }

    /**
     * `GET /v1/instance/integrations/chatly` — the Chatly connection (enabled: false when none).
     *
     * @return ChatlyIntegration
     */
    public function getChatlyIntegration(): array
    {
        return $this->http->request('GET', '/v1/instance/integrations/chatly');
    }

    /**
     * `PUT /v1/instance/integrations/chatly` — connect a Chatly channel.
     *
     * @param array{channel:string} $body
     *
     * @return ChatlyIntegration
     */
    public function setChatlyIntegration(array $body): array
    {
        return $this->http->request('PUT', '/v1/instance/integrations/chatly', body: $body);
    }

    /**
     * `DELETE /v1/instance/integrations/chatly` — remove the Chatly connection.
     *
     * @return ChatlyIntegration
     */
    public function deleteChatlyIntegration(): array
    {
        return $this->http->request('DELETE', '/v1/instance/integrations/chatly');
    }

    /**
     * `GET /v1/instance/integrations/forumulate` — the Forumulate connection (enabled: false when none).
     *
     * @return ForumulateIntegration
     */
    public function getForumulateIntegration(): array
    {
        return $this->http->request('GET', '/v1/instance/integrations/forumulate');
    }

    /**
     * `PUT /v1/instance/integrations/forumulate` — connect Forumulate spaces (replaces the whole
     * connection). Store the returned `reader_auth_provider` on each space.
     *
     * @param ForumulateIntegrationInput $body
     *
     * @return ForumulateIntegration
     */
    public function setForumulateIntegration(array $body): array
    {
        return $this->http->request('PUT', '/v1/instance/integrations/forumulate', body: $body);
    }

    /**
     * `DELETE /v1/instance/integrations/forumulate` — remove the Forumulate connection.
     *
     * @return ForumulateIntegration
     */
    public function deleteForumulateIntegration(): array
    {
        return $this->http->request('DELETE', '/v1/instance/integrations/forumulate');
    }

    /**
     * `GET /v1/instance/cookie_consent` — the cookie-consent script injected into the hosted pages
     * (enabled: false when none), with its `settings_link` (null when none), `culture` and
     * `declared_cookies` (every cookie and storage key Atlas writes, for your cookie policy).
     *
     * @return CookieConsent
     */
    public function getCookieConsent(): array
    {
        return $this->http->request('GET', '/v1/instance/cookie_consent');
    }

    /**
     * `PUT /v1/instance/cookie_consent` — set the cookie-consent script for the hosted pages (replaces the
     * whole setting). `script_url` and every `extra_origins` entry must be https;
     * their origins are added to the page's Content-Security-Policy.
     *
     * `settings_link` (optional; null or omitted = no link) adds a "cookie settings" link to the
     * hosted pages: `label` (required, 1-60 chars), `preset` (one of
     * {@see self::COOKIE_SETTINGS_LINK_PRESETS}, or null) and `attributes` (allowed names `data-*`,
     * `aria-*`, `id`, `class`, `href` (https or a `#fragment`); at most 10; values up to 200 chars).
     * A link needs a preset or at least one attribute (400 otherwise). The response's `settings_link`
     * carries `label`, `preset`, `attributes` and `rendered_attributes` — the attributes the rendered
     * link carries: yours plus the preset's trigger.
     *
     *     $atlas->instance->setCookieConsent([
     *         'script_url' => 'https://cmp.example.com/loader.js',
     *         'settings_link' => ['label' => 'Cookie settings', 'preset' => Instance::COOKIE_SETTINGS_LINK_PRESET_COOKIEBOT],
     *     ]);
     *
     * @param CookieConsentInput $body
     *
     * @return CookieConsent
     */
    public function setCookieConsent(array $body): array
    {
        return $this->http->request('PUT', '/v1/instance/cookie_consent', body: $body);
    }

    /**
     * `DELETE /v1/instance/cookie_consent` — turn the hosted-page cookie-consent script off.
     *
     * @return CookieConsent
     */
    public function deleteCookieConsent(): array
    {
        return $this->http->request('DELETE', '/v1/instance/cookie_consent');
    }

    /**
     * Write `auth_config.accountLinkingTrust`: keys are an exact provider key, a `prefix*`
     * or `*`; values `always` (vouch for the provider's email, for linking only),
     * `verified` or `never` (refused with 409 `IDENTITY_ALREADY_LINKED`). An account that
     * never proved its email is never linked. See {@see \Atlas\Constants::LINKING_TRUST_LEVELS}.
     *
     * @param array<string,'always'|'verified'|'never'> $trust
     *
     * @return array<string,mixed>
     */
    public function setAccountLinkingTrust(array $trust): array
    {
        return $this->update(['auth_config' => ['accountLinkingTrust' => $trust === [] ? new \stdClass() : $trust]]);
    }

    /**
     * Merge `auth_config.captcha`: `provider` (see {@see \Atlas\Constants::CAPTCHA_PROVIDERS}),
     * `siteKey`, `widget`, `minScore`, per-flow / per-action overrides and, for Vercel
     * BotID, `attestationUrl` (https or a root-relative path).
     *
     * @param array<string,mixed> $captcha
     *
     * @return array<string,mixed>
     */
    public function setCaptchaConfig(array $captcha): array
    {
        return $this->update(['auth_config' => ['captcha' => $captcha]]);
    }

    /**
     * `GET /v1/instance/unknown_fields_report?days=` — the unknown body fields your writes sent over the last `days` (1-90, default 30). Fix every row with an `ignored_count`, then turn strict request bodies on.
     *
     * @return array<string,mixed>
     */
    public function unknownFieldsReport(array $params = []): array
    {
        return $this->http->request('GET', '/v1/instance/unknown_fields_report', query: $params);
    }

    /**
     * `GET /v1/openapi/diff` — what changed in the API since a date (`since` YYYY-MM-DD, optional `surface`): operations added, deprecated and removed.
     *
     * @return array<string,mixed>
     */
    public function openapiDiff(string $since, ?string $surface = null): array
    {
        return $this->http->request('GET', '/v1/openapi/diff', query: ['since' => $since, 'surface' => $surface]);
    }

    /**
     * `GET /v1/integration-health` — one report of everything connecting the instance to the outside (setup checks, webhooks, back-channel logout, referrals, email, consent manager, SDK versions). `$only` limits the sources; `$probe = false` skips live reachability probes.
     *
     * @return array<string,mixed>
     */
    public function integrationHealth(?array $only = null, ?bool $probe = null): array
    {
        return $this->http->request('GET', '/v1/integration-health', query: ['only' => $only === null ? null : implode(',', $only), 'probe' => $probe]);
    }

    /**
     * `GET /v1/integration-health/providers` — the check sources this deployment reports.
     *
     * @return array<string,mixed>
     */
    public function integrationHealthProviders(): array
    {
        return $this->http->request('GET', '/v1/integration-health/providers');
    }

    /**
     * `GET /v1/embed` — the embed manifest of an instance (public): CSP sources per surface with its hosts filled in, and the merged policy in `tenant.policy`. `shape`: `redirect`|`panel`|`button`.
     *
     * @return array<string,mixed>
     */
    public function embed(string $instance, ?string $origin = null, ?string $shape = null): array
    {
        return $this->http->request('GET', '/v1/embed', query: ['instance' => $instance, 'origin' => $origin, 'shape' => $shape]);
    }
}
