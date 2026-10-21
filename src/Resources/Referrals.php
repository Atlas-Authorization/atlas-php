<?php

declare(strict_types=1);

namespace Atlas\Resources;

/**
 * `/v1/instance/referrals` — referral attribution: the instance's connection to a
 * referral platform, the connection test and the call log.
 *
 * With referrals on, every interactive sign-up is reported to the platform before
 * `user.created` is sent; the answer lands on the user (`referral`, `referral_code`,
 * `referral_link`). A late answer arrives as the `user.referral_attributed` webhook.
 * `signing_secret` and `api_key` are write-only: reads return `signing_secret_set`
 * / `api_key_set`.
 */
final class Referrals extends Resource
{
    /** @return array<string,mixed> the settings, `connection` status and supported `providers` */
    public function get(): array
    {
        return $this->http->request('GET', '/v1/instance/referrals');
    }

    /**
     * Partial update: only the keys sent change, `null` clears. `security` is partial too
     * (`challenge_referred_signups`, `on_high_risk`, `hold_until_email_verified`,
     * `max_referred_signups_per_ip_hour`). Enabling without what the provider needs is
     * 422 `REFERRALS_NOT_CONFIGURED`.
     *
     * @param array<string,mixed> $body {enabled?, provider?, endpoint_url?, program_id?, connection_id?,
     *   signing_secret?, api_key?, url_params?, context_cookie?, consent_category?, consent_fallback_category?,
     *   auto_enroll_users?, link_template?, timeout_ms?, security?}
     *
     * @return array<string,mixed>
     */
    public function update(array $body, ?string $idempotencyKey = null): array
    {
        return $this->http->request('PATCH', '/v1/instance/referrals', body: $body, idempotencyKey: $idempotencyKey);
    }

    /**
     * A signed probe of the platform: `ok` on 2xx or 404; `error.code` `signature_rejected` on 401/403.
     *
     * @return array<string,mixed> {ok, http_status, latency_ms, error?}
     */
    public function test(): array
    {
        return $this->http->request('POST', '/v1/instance/referrals/test');
    }

    /**
     * The call log, newest first.
     *
     * @param array{limit?:int,status?:string,kind?:string,user_id?:string} $params
     *
     * @return array<string,mixed> {object: list, data, has_more}
     */
    public function listCalls(array $params = []): array
    {
        return $this->http->request('GET', '/v1/instance/referrals/calls', query: $params === [] ? null : $params);
    }

    /**
     * Retry a failed, retrying or skipped call now (not a connection test).
     *
     * @return array<string,mixed> the ReferralCall
     */
    public function retryCall(string $id, ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'POST',
            '/v1/instance/referrals/calls/' . rawurlencode($id) . '/retry',
            idempotencyKey: $idempotencyKey,
        );
    }
}
