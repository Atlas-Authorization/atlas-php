<?php

declare(strict_types=1);

namespace Atlas\Resources;

/**
 * `/v1/instance/referrals` — referral attribution: the instance's connection to a
 * referral platform, the connection test and the call log.
 *
 * With referrals on, every interactive sign-up is reported to the platform before
 * `user.created` is sent; the answer lands on the user (`referral`, `referral_code`,
 * `referral_link`). A late answer arrives as the `user.referral_resolved` webhook (and
 * `user.referral_attributed` when it attributes the user). `provider` is `webhook` (the Atlas
 * referral protocol), `referally` or another built-in adapter.
 * `signing_secret`, `api_key` and `inbound_secret` are write-only: reads return
 * `signing_secret_set` / `api_key_set` / `inbound.secret_set`.
 *
 * Two-way sync: a provider with the `inbound` capability sends referral changes made after
 * sign-up to `inbound.url`; reads report `inbound` (`url`, `secret_set`, `endpoint_id`,
 * `last_event` with `at`, `type`, `outcome`).
 *
 * Browser capture: `browser_sources` lists the referral programs whose script or cookie the
 * browser reads, in order (`referally`, `rewardful`, `tolt`, `promotekit`, `firstpromoter`,
 * `partnerstack`, `dub`; `null` goes back to the provider's default, reported by
 * `browser_sources_default`), and `consent_provider` picks the consent manager that decides
 * (`auto`, one manager such as `onetrust`, or `app`).
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
     * 422 `REFERRALS_NOT_CONFIGURED`. Reads add `connection.last_ping` and `connection.alert`
     * (`signature_rejected` or `connection_paused` while delivery is blocked).
     *
     * @param array<string,mixed> $body {enabled?, provider?, endpoint_url?, program_id?, connection_id?,
     *   signing_secret?, api_key?, inbound_secret?, inbound_endpoint_id?, url_params?, context_cookie?, consent_category?, consent_fallback_category?,
     *   auto_enroll_users?, link_template?, timeout_ms?, security?, tracking_token?, code_lookup_url?,
     *   signup_code_field?, browser_sources?, consent_provider?, forward_lifecycle?, mirror_to_public_metadata?,
     *   include_referral_claims?}
     *
     * @return array<string,mixed>
     */
    public function update(array $body, ?string $idempotencyKey = null): array
    {
        return $this->http->request('PATCH', '/v1/instance/referrals', body: $body, idempotencyKey: $idempotencyKey);
    }

    /**
     * Test the connection: a ping where the provider answers one (`method` `ping`, with `ping`
     * and `warnings` such as clock skew), else a signed probe (`method` `probe`: `ok` on 2xx or
     * 404). `error.code` is `signature_rejected` on 401/403.
     *
     * @return array<string,mixed> {ok, http_status, latency_ms, method?, ping?, warnings?, error?}
     */
    public function test(): array
    {
        return $this->http->request('POST', '/v1/instance/referrals/test');
    }

    /**
     * The call log, newest first. `limit` is 1-1000 (default 50; servers before referral
     * attribution v2 take at most 200); page with `starting_after` (the id of the last call of
     * the previous page) while `has_more`. Calls carry `error_code`, `latency_ms`, `request_id`,
     * `response`, `highlights` and `batch_id`.
     *
     * @param array{limit?:int,status?:string,kind?:string,user_id?:string,error_code?:string,starting_after?:string} $params
     *
     * @return array<string,mixed> {object: list, data, has_more}
     */
    public function listCalls(array $params = []): array
    {
        return $this->http->request('GET', '/v1/instance/referrals/calls', query: $params === [] ? null : $params);
    }

    /**
     * One call of the delivery log, by its id or the platform's request id.
     *
     * @return array<string,mixed>
     */
    public function getCall(string $id): array
    {
        return $this->http->request('GET', '/v1/instance/referrals/calls/' . rawurlencode($id));
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

    /**
     * Re-queue every failed call (not tests) to send now, optionally only one `kind` or one
     * `error_code` (e.g. `signature_rejected` after replacing the secret).
     *
     * @param array{kind?:string,error_code?:string} $filter
     *
     * @return array<string,mixed> {object: referral_retry, requeued}
     */
    public function retryFailed(array $filter = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'POST',
            '/v1/instance/referrals/calls/retry_failed',
            body: $filter === [] ? new \stdClass() : $filter,
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * Send a sign-up call for a made-up user (`atlas_test_<random>`; no user is created) with
     * an optional code, and return what the platform answered.
     *
     * @return array<string,mixed> {object: referral_simulation, ok, call_id, customer_ref, http_status,
     *   latency_ms, result, response, error}
     */
    public function simulate(?string $code = null): array
    {
        return $this->http->request(
            'POST',
            '/v1/instance/referrals/simulate',
            body: $code === null ? new \stdClass() : ['code' => $code],
        );
    }

    /**
     * Queue sign-up calls for existing users created at or after `since` (ISO 8601), with
     * their real sign-up time. Users already reported are skipped. Referrals must be on (422
     * `REFERRALS_NOT_CONFIGURED` otherwise). Poll {@see getBackfill()}.
     *
     * @param array{since:string,codes?:array<string,string>,code_metadata_path?:string} $body
     *
     * @return array<string,mixed> the referral_backfill job
     */
    public function backfill(array $body, ?string $idempotencyKey = null): array
    {
        return $this->http->request('POST', '/v1/instance/referrals/backfill', body: $body, idempotencyKey: $idempotencyKey);
    }

    /**
     * A backfill job: `status` (`running`, then `completed`), `users_scanned`, `users_skipped`,
     * `calls_queued`, `truncated`, `next_since`, `calls`.
     *
     * @return array<string,mixed>
     */
    public function getBackfill(string $id): array
    {
        return $this->http->request('GET', '/v1/instance/referrals/backfill/' . rawurlencode($id));
    }

    /**
     * `GET /v1/users/:id/referral` — the stored result for one user (`status`, `customer_ref`,
     * `partner_external_ref`, `referral_status`, `notes`, ...), with no call to the platform.
     * 404 when the user was never reported.
     *
     * @return array<string,mixed> the user_referral
     */
    public function getForUser(string $userId): array
    {
        return $this->http->request('GET', '/v1/users/' . rawurlencode($userId) . '/referral');
    }
}
