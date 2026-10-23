<?php

declare(strict_types=1);

namespace Atlas\Resources;

/**
 * `/v1/instance/security` — §5/§11.2 instance security config, the agent mirror
 * of the dashboard security screen. Settings that live OUTSIDE `auth_config` (so
 * a raw `PATCH /v1/instance` cannot reach them): the per-flow kill switches, the
 * customer IP allowlist, and the write-only provider secrets (captcha, Kerberos
 * proxy, LDAP bind password). No secret is ever readable back.
 */
final class InstanceSecurity extends Resource
{
    /** @return array<string,mixed> the InstanceSecurity object */
    public function get(): array
    {
        return $this->http->request('GET', '/v1/instance/security');
    }

    /**
     * @param array{kill_switches?:array<string,mixed>,ip_allowlist?:list<string>,confirm_lockout?:bool} $body
     *
     * @return array<string,mixed> the config, plus a plain-language `warning` on a confirmed self-lockout
     */
    public function update(array $body): array
    {
        return $this->http->request('PATCH', '/v1/instance/security', body: $body);
    }

    /**
     * The published token-signing keys: `current` and, inside a rotation's
     * grace window, `previous` with `retires_at`.
     *
     * @return array<string,mixed>
     */
    public function listSigningKeys(): array
    {
        return $this->http->request('GET', '/v1/instance/signing_keys');
    }

    /**
     * Rotate the instance signing key, optionally switching `alg` (RS256 or ES256).
     *
     * @param array{alg?:'RS256'|'ES256',grace_period_seconds?:int,emergency?:bool} $body
     *
     * @return array<string,mixed>
     */
    public function rotateSigningKey(array $body = []): array
    {
        return $this->http->request('POST', '/v1/instance/signing_keys/rotate', body: $body);
    }

    /**
     * Store a captcha provider secret. `$provider` (default: the configured one) lets you
     * store it BEFORE turning captcha on — captcha is bring-your-own-keys, and enabling it
     * without a site key and secret is refused with 422 `CAPTCHA_KEYS_REQUIRED`.
     *
     * @return array<string,mixed>
     */
    public function setCaptchaSecret(string $secret, ?string $provider = null): array
    {
        $body = ['secret' => $secret];
        if ($provider !== null) {
            $body['provider'] = $provider;
        }
        return $this->http->request('PUT', '/v1/instance/captcha_secret', body: $body);
    }

    /**
     * Remove a captcha secret. Refused with 409 `CAPTCHA_IN_USE` while a flow uses it
     * (`meta.flows`); `force: true` removes it anyway and turns those flows off
     * (returned as `flows_turned_off`).
     *
     * @return array<string,mixed>
     */
    public function deleteCaptchaSecret(?string $provider = null, bool $force = false): array
    {
        $query = [];
        if ($provider !== null) {
            $query['provider'] = $provider;
        }
        if ($force) {
            $query['force'] = true;
        }
        return $this->http->request('DELETE', '/v1/instance/captcha_secret', query: $query === [] ? null : $query);
    }

    /**
     * Store the Kerberos/IWA trusted-proxy secret. Refused while the strategy is off.
     *
     * @return array<string,mixed>
     */
    public function setKerberosSecret(string $secret): array
    {
        return $this->http->request('PUT', '/v1/instance/kerberos_secret', body: ['secret' => $secret]);
    }

    /** @return array<string,mixed> */
    public function deleteKerberosSecret(): array
    {
        return $this->http->request('DELETE', '/v1/instance/kerberos_secret');
    }

    /**
     * `GET /v1/instance/proxy_secret` — whether a trusted reverse proxy secret is set
     * (`set`, `created_at`, `previous_expires_at`, `secret_header`, `client_ip_header`).
     * Never the secret.
     *
     * @return array{object:'proxy_secret',set:bool,created_at:int|null,previous_expires_at:int|null,secret_header:string,client_ip_header:string}
     */
    public function getProxySecret(): array
    {
        return $this->http->request('GET', '/v1/instance/proxy_secret');
    }

    /**
     * `POST /v1/instance/proxy_secret` — create, or rotate, the trusted reverse proxy
     * secret. The response adds `secret` (shown once) and `rotated`. On a rotation the
     * replaced secret keeps working for `overlap_seconds` (default 86400, `0` stops it
     * now, at most 604800).
     *
     * @param array{overlap_seconds?:int|null}|null $body
     *
     * @return array{object:'proxy_secret',set:bool,created_at:int|null,previous_expires_at:int|null,secret_header:string,client_ip_header:string,secret:string,rotated:bool}
     */
    public function createProxySecret(?array $body = null): array
    {
        return $this->http->request('POST', '/v1/instance/proxy_secret', body: $body);
    }

    /**
     * `DELETE /v1/instance/proxy_secret` — remove the proxy secret: `X-Atlas-Client-IP`
     * is ignored again.
     *
     * @return array<string,mixed>
     */
    public function deleteProxySecret(): array
    {
        return $this->http->request('DELETE', '/v1/instance/proxy_secret');
    }

    /**
     * Store an LDAP connection's service-account bind password. The connection
     * must already exist in `auth_config.ldap.connections`.
     *
     * @return array<string,mixed>
     */
    public function setLdapBindPassword(string $connectionId, string $bindPassword): array
    {
        return $this->http->request(
            'PUT',
            '/v1/instance/ldap_connections/' . rawurlencode($connectionId) . '/bind_password',
            body: ['bind_password' => $bindPassword],
        );
    }

    /** @return array<string,mixed> */
    public function deleteLdapBindPassword(string $connectionId): array
    {
        return $this->http->request(
            'DELETE',
            '/v1/instance/ldap_connections/' . rawurlencode($connectionId) . '/bind_password',
        );
    }

    /**
     * `GET /v1/instance/signing_key_provider` — the key management service the signing key
     * lives in (`local` when none). Never carries credentials.
     *
     * @return array<string,mixed>
     */
    public function getSigningKeyProvider(): array
    {
        return $this->http->request('GET', '/v1/instance/signing_key_provider');
    }

    /**
     * `PUT /v1/instance/signing_key_provider` — sign every token with a key in your own KMS.
     *  - `aws_kms`: config `{region, key_id?, alg?}`, credentials `{access_key_id, secret_access_key, session_token?}`
     *  - `gcp_kms`: config `{crypto_key, key_version?}`, credentials `{service_account_json}`
     *  - `azure_key_vault`: config `{vault_url, key_name, key_version?, alg?}`, credentials `{tenant_id, client_id, client_secret}`
     *
     * @param array{provider:'aws_kms'|'gcp_kms'|'azure_key_vault',config:array<string,mixed>,credentials:array<string,mixed>,grace_period_seconds?:int,emergency?:bool} $body
     *
     * @return array<string,mixed> the settings plus `rotation`
     */
    public function setSigningKeyProvider(array $body): array
    {
        return $this->http->request('PUT', '/v1/instance/signing_key_provider', body: $body);
    }

    /**
     * `DELETE /v1/instance/signing_key_provider` — rotate back to a key Atlas holds and
     * forget the KMS credentials.
     *
     * @param array{grace_period_seconds?:int,emergency?:bool} $body
     *
     * @return array<string,mixed>
     */
    public function removeSigningKeyProvider(array $body = []): array
    {
        return $this->http->request('DELETE', '/v1/instance/signing_key_provider', body: $body);
    }

    /**
     * `PUT /v1/instance/wallet_rpc_secret` — store (write-only, encrypted) the wallet RPC
     * endpoint for an EIP-155 chain, used by wallet token gating and the Sign in with
     * Ethereum ENS lookup. An empty url removes it.
     *
     * @return array<string,mixed>
     */
    public function setWalletRpcSecret(int $chainId, string $url): array
    {
        return $this->http->request('PUT', '/v1/instance/wallet_rpc_secret', body: ['chain_id' => $chainId, 'url' => $url]);
    }
}
