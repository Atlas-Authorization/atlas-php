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
}
