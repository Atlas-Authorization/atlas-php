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
 * @phpstan-type CookieConsent array{object:string,enabled:bool,script_url?:string,script_id?:string|null,site_id?:string|null,blocking_mode?:string|null,extra_origins?:list<string>}
 * @phpstan-type CookieConsentInput array{script_url:string,script_id?:string|null,site_id?:string|null,blocking_mode?:string|null,extra_origins?:list<string>}
 */
final class Instance extends Resource
{
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
     * (enabled: false when none).
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
