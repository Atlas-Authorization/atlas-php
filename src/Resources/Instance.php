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
 * @phpstan-type PreflightReport array{object:string,ok:bool,checked_at:int,findings:list<PreflightFinding>}
 * @phpstan-type CookieSettingsLinkInput array{label:string,preset?:string|null,attributes?:array<string,string>}
 * @phpstan-type CookieSettingsLink array{label:string,preset:string|null,attributes:array<string,string>,rendered_attributes:array<string,string>}
 * @phpstan-type CookieConsent array{object:string,enabled:bool,script_url?:string,script_id?:string|null,site_id?:string|null,blocking_mode?:string|null,extra_origins?:list<string>,settings_link?:CookieSettingsLink|null}
 * @phpstan-type CookieConsentInput array{script_url:string,script_id?:string|null,site_id?:string|null,blocking_mode?:string|null,extra_origins?:list<string>,settings_link?:CookieSettingsLinkInput|null}
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

    /** @return array<string,mixed> the Instance object */
    public function get(): array
    {
        return $this->http->request('GET', '/v1/instance');
    }

    /**
     * `PATCH /v1/instance` echoes only the mutable fields, not a full Instance.
     *
     * @param array{allowed_origins?:list<string>,auth_config?:array<string,mixed>} $body
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
     * @return PreflightReport
     */
    public function preflight(): array
    {
        return $this->http->request('GET', '/v1/instance/preflight');
    }

    /**
     * `GET /v1/instance/cookie_consent` — the cookie-consent script injected into the hosted pages
     * (enabled: false when none), with its `settings_link` (null when none).
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
}
