<?php

declare(strict_types=1);

namespace Atlas\Resources;

/** `/v1/oauth_providers` — the social sign-in provider catalog + per-instance config. */
final class OAuthProviders extends Resource
{
    /** The whole catalog, configured or not. @return array<string,mixed> a ListPage of providers */
    public function list(): array
    {
        return $this->http->request('GET', '/v1/oauth_providers');
    }

    /** @return array<string,mixed> */
    public function get(string $provider): array
    {
        return $this->http->request('GET', '/v1/oauth_providers/' . rawurlencode($provider));
    }

    /**
     * Set (or replace) this instance's own credentials for a provider.
     *
     * @param array{client_id?:string,client_secret?:string,scopes?:list<string>,values?:array<string,string>,settings?:array<string,string>} $body
     *
     * @return array<string,mixed>
     */
    public function upsert(string $provider, array $body, ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'PUT',
            '/v1/oauth_providers/' . rawurlencode($provider),
            body: $body,
            idempotencyKey: $idempotencyKey,
        );
    }

    /** @return array<string,mixed> */
    public function delete(string $provider): array
    {
        return $this->http->request('DELETE', '/v1/oauth_providers/' . rawurlencode($provider));
    }

    /** Turn a configured provider on or off. @return array<string,mixed> */
    public function setEnabled(string $provider, bool $enabled): array
    {
        return $this->http->request(
            'POST',
            '/v1/oauth_providers/' . rawurlencode($provider) . '/enabled',
            body: ['enabled' => $enabled],
        );
    }

    /**
     * §6.5 which screens this provider serves. At least one must be true.
     *
     * @param array{allow_sign_in:bool,allow_sign_up:bool} $body
     *
     * @return array<string,mixed>
     */
    public function setScope(string $provider, array $body): array
    {
        return $this->http->request(
            'POST',
            '/v1/oauth_providers/' . rawurlencode($provider) . '/scope',
            body: $body,
        );
    }

    /** Verify the stored credentials against the provider's token endpoint. @return array<string,mixed> */
    public function test(string $provider): array
    {
        return $this->http->request('POST', '/v1/oauth_providers/' . rawurlencode($provider) . '/test');
    }
}
