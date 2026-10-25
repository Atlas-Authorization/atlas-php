<?php

declare(strict_types=1);

namespace Atlas\Resources;

/**
 * `/v1/partner_platforms` — this instance as the PARTNER PLATFORM of another product: a product that signs its partner
 * deliveries with a published Ed25519 key set. The partner delivers signed events to the instance's receiver; it
 * registers from the {@see registration()} details, and the partner token it hands back is stored once, encrypted, and
 * never returned by any call (`token_set` and `token_prefix` only).
 */
final class PartnerPlatforms extends Resource
{
    /**
     * The partner platforms and the ready-made presets.
     *
     * @return array<string,mixed> `{object:'list', data:[PartnerPlatform], has_more, presets:[...]}`
     */
    public function list(): array
    {
        return $this->http->request('GET', '/v1/partner_platforms');
    }

    /**
     * One partner platform: `{partner, name, preset, platform_id, enabled, jwks_url, signature_header, signature_scheme,
     * tolerance_seconds, allowed_sources, record_consents, receiver_url, receiver_url_template, serves_deployment,
     * registration: {status: unregistered|registered, registered_at}, token_set, token_prefix, last_delivery_at,
     * last_delivery_error, created_at, updated_at}`.
     *
     * @return array<string,mixed>
     */
    public function get(string $partner): array
    {
        return $this->http->request('GET', self::path($partner));
    }

    /**
     * Set up or change a partner platform (a preset partner fills the defaults).
     *
     * @param array{name?:?string,platform_id?:string,enabled?:bool,jwks_url?:string,signature_header?:string,signature_scheme?:'t_kid_ed25519',tolerance_seconds?:int,allowed_sources?:list<string>,record_consents?:bool,serves_deployment?:bool} $body
     *
     * @return array<string,mixed>
     */
    public function set(string $partner, array $body = []): array
    {
        return $this->http->request('PUT', self::path($partner), body: $body === [] ? new \stdClass() : $body);
    }

    /**
     * Remove a partner platform and its stored token.
     *
     * @return array<string,mixed> `{object:'partner_platform', partner, deleted:true}`
     */
    public function remove(string $partner): array
    {
        return $this->http->request('DELETE', self::path($partner));
    }

    /**
     * What to send the partner to be registered: the receiver URL, the signature it must use and where to send it.
     *
     * @return array<string,mixed>
     */
    public function registration(string $partner): array
    {
        return $this->http->request('GET', self::path($partner, '/registration'));
    }

    /**
     * Store the partner token the partner handed over (once; `rotate` replaces a stored one). The token is write-only:
     * it travels in the request body only, and the answer (the partner platform) never contains it.
     *
     * @return array<string,mixed>
     */
    public function storeToken(string $partner, string $token, bool $rotate = false, ?string $apiBaseUrl = null): array
    {
        $body = ['token' => $token];
        if ($rotate) {
            $body['rotate'] = true;
        }
        if ($apiBaseUrl !== null) {
            $body['api_base_url'] = $apiBaseUrl;
        }

        return $this->http->request('POST', self::path($partner, '/token'), body: $body);
    }

    /**
     * The latest signed deliveries received from the partner: `{event_id, event_type, outcome: recorded|unchanged|no_subject|no_user|source_not_allowed|ignored, user_id, received_at}`.
     *
     * @return array<string,mixed>
     */
    public function deliveries(string $partner, ?int $limit = null): array
    {
        return $this->http->request('GET', self::path($partner, '/deliveries'), query: $limit ? ['limit' => $limit] : null);
    }

    private static function path(string $partner, string $rest = ''): string
    {
        return '/v1/partner_platforms/' . rawurlencode($partner) . $rest;
    }
}
