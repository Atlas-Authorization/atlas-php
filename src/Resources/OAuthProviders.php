<?php

declare(strict_types=1);

namespace Atlas\Resources;

/** `/v1/oauth_providers` — the social sign-in provider catalog + per-instance config. */
final class OAuthProviders extends Resource
{
    /**
     * The whole catalog, configured or not. Each entry carries `icon_url`, `mode`
     * and, for wallet providers, `setting_values`.
     *
     *     $atlas->oauthProviders->list(['configured' => true]);
     *
     * @param array{provider?:string,configured?:bool} $params optional filters
     *
     * @return array<string,mixed> a ListPage of providers
     */
    public function list(array $params = []): array
    {
        return $this->http->request('GET', '/v1/oauth_providers', query: $params);
    }

    /** @return array<string,mixed> */
    public function get(string $provider): array
    {
        return $this->http->request('GET', '/v1/oauth_providers/' . rawurlencode($provider));
    }

    /**
     * Set (or replace) this instance's own credentials for a provider.
     *
     * Besides credentials, accepts display-only fields that can be sent alone:
     * `display_name` and `icon_url` (how Atlas labels the button) and `mode`
     * (`native` or `redirect`; `''` or null clears the override). Providers with
     * no client credentials (`steam`, `siwe`, `siws`, `siwf`) take only display
     * fields and, for the wallets, verifier `settings` — siwe: `domain`,
     * `chain_ids`, `rpc_url`; siws: `domain`, `networks`; siwf: `domain`,
     * `chain_ids`, `optimism_rpc_url`, `id_registry_address`. A null (or `''`)
     * setting value clears it; stored values come back as `setting_values`.
     *
     *     $atlas->oauthProviders->upsert('siwe', ['settings' => ['domain' => 'app.example.com', 'chain_ids' => [1, 10]]]);
     *
     * @param array{client_id?:string,client_secret?:string,scopes?:list<string>,values?:array<string,string>,settings?:array<string,mixed>,custom_redirect_uri?:string,native?:bool,mode?:'native'|'redirect'|''|null,display_name?:string|null,icon_url?:string|null} $body
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
