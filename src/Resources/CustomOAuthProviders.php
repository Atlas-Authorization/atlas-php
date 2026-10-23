<?php

declare(strict_types=1);

namespace Atlas\Resources;

/**
 * `/v1/custom_oauth_providers` — your own upstream OAuth 2.0 / OpenID Connect login
 * providers, with Atlas as the relying party: endpoints (or just an OIDC issuer),
 * token-endpoint client authentication (incl. `private_key_jwt` with an Atlas-held key),
 * a declarative `profile_mapping`, upstream `logout` and `idp_initiated_login`. The
 * provider then signs users in like any built-in one, under its `provider_key`.
 */
final class CustomOAuthProviders extends Resource
{
    /** @return array<string,mixed> a ListPage of providers */
    public function list(): array
    {
        return $this->http->request('GET', '/v1/custom_oauth_providers');
    }

    /** @return array<string,mixed> */
    public function get(string $id): array
    {
        return $this->http->request('GET', '/v1/custom_oauth_providers/' . rawurlencode($id));
    }

    /**
     * @param array<string,mixed> $body `provider_key` and `display_name` are required; `client_secret` is write-only
     *
     * @return array<string,mixed>
     */
    public function create(array $body, ?string $idempotencyKey = null): array
    {
        return $this->http->request('POST', '/v1/custom_oauth_providers', body: $body, idempotencyKey: $idempotencyKey);
    }

    /**
     * Patch a provider; a null value clears a nullable setting.
     *
     * @param array<string,mixed> $body
     *
     * @return array<string,mixed>
     */
    public function update(string $id, array $body): array
    {
        return $this->http->request('PATCH', '/v1/custom_oauth_providers/' . rawurlencode($id), body: $body);
    }

    /** @return array<string,mixed> */
    public function delete(string $id): array
    {
        return $this->http->request('DELETE', '/v1/custom_oauth_providers/' . rawurlencode($id));
    }

    /**
     * Fetch and validate an issuer's OpenID configuration (for autofill).
     *
     * @return array<string,mixed>
     */
    public function discover(string $issuer, ?string $discoveryUrl = null): array
    {
        $body = ['issuer' => $issuer];
        if ($discoveryUrl !== null) {
            $body['discovery_url'] = $discoveryUrl;
        }

        return $this->http->request('POST', '/v1/custom_oauth_providers/discover', body: $body);
    }

    /**
     * Run a mapping against a pasted sample (`profile_mapping`, `userinfo`,
     * `id_token_claims`, `id_token`). With `$id` the provider's stored mapping is used
     * unless the body carries one.
     *
     * @param array<string,mixed> $body
     *
     * @return array<string,mixed> `profile`, `trace`, `warnings`
     */
    public function previewProfileMapping(array $body, ?string $id = null): array
    {
        $path = $id === null
            ? '/v1/custom_oauth_providers/profile_mapping/preview'
            : '/v1/custom_oauth_providers/' . rawurlencode($id) . '/profile_mapping/preview';

        return $this->http->request('POST', $path, body: $body);
    }

    /** @return array<string,mixed> a ListPage of the provider's private_key_jwt keys */
    public function listSigningKeys(string $id): array
    {
        return $this->http->request('GET', '/v1/custom_oauth_providers/' . rawurlencode($id) . '/signing_keys');
    }

    /**
     * Generate a new active key (`RS256`, `PS256` or `ES256`); the previous one turns
     * `retiring` (still published).
     *
     * @return array<string,mixed>
     */
    public function rotateSigningKey(string $id, ?string $alg = null, ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'POST',
            '/v1/custom_oauth_providers/' . rawurlencode($id) . '/signing_keys',
            body: $alg === null ? [] : ['alg' => $alg],
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * Retire a non-active key (it leaves the JWKS).
     *
     * @return array<string,mixed>
     */
    public function retireSigningKey(string $id, string $kid): array
    {
        return $this->http->request('POST', '/v1/custom_oauth_providers/' . rawurlencode($id) . '/signing_keys/' . rawurlencode($kid) . '/retire');
    }
}
