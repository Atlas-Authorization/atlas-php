<?php

declare(strict_types=1);

namespace Atlas\Resources;

/** `/v1/risk_based_mfa` — the adaptive/risk-based MFA engine config. */
final class RiskBasedMfa extends Resource
{
    /** @return array<string,mixed> */
    public function get(): array
    {
        return $this->http->request('GET', '/v1/risk_based_mfa');
    }

    /**
     * @param array{enabled?:bool,step_up_threshold?:string,on_high_risk_no_factor?:string,weights?:array<string,mixed>,signals?:array<string,mixed>,mode?:string,level_actions?:array<string,mixed>,reputation?:array<string,mixed>,fraud?:array<string,mixed>,baseline?:array<string,mixed>,mid_session?:array<string,mixed>} $body
     *
     * @return array<string,mixed>
     */
    public function update(array $body): array
    {
        return $this->http->request('PATCH', '/v1/risk_based_mfa', body: $body);
    }

    /**
     * The risk-event timeline (every scored sign-in), newest first. `before` is an ISO
     * timestamp cursor on `created_at`; `limit` is 1-200.
     *
     * @param array{user_id?:string,level?:string,limit?:int,before?:string} $query
     *
     * @return array<string,mixed> a `{ object: "list", data: [risk_event] }` page
     */
    public function listEvents(array $query = []): array
    {
        return $this->http->request('GET', '/v1/risk_events', query: $query);
    }

    /**
     * Counts per level/action/mode over the last `days` (1-90, default 7).
     *
     * @param array{days?:int} $query
     *
     * @return array<string,mixed> a `risk_event_metrics` object
     */
    public function eventMetrics(array $query = []): array
    {
        return $this->http->request('GET', '/v1/risk_events/metrics', query: $query);
    }

    /**
     * Mark a scored sign-in `legit` or `fraud`; the weight of each signal that fired moves
     * accordingly.
     *
     * @param 'legit'|'fraud' $verdict
     *
     * @return array<string,mixed> the updated `risk_event`
     */
    public function eventFeedback(string $id, string $verdict): array
    {
        return $this->http->request('POST', '/v1/risk_events/' . rawurlencode($id) . '/feedback', body: ['verdict' => $verdict]);
    }

    /**
     * Allow / challenge / block verdict for a sign-up, sign-in or checkout.
     *
     * @param array{ip:string,user_agent?:string,device_id?:string,email?:string,user_id?:string,org_id?:string,device?:array<string,mixed>} $body
     *
     * @return array<string,mixed> a `fraud_lookup` object
     */
    public function fraudLookup(array $body): array
    {
        return $this->http->request('POST', '/v1/fraud/lookup', body: $body);
    }

    /**
     * `PUT /v1/risk_based_mfa/reputation_secret` - Store (write-only, encrypted) the API key of your
     * IP-reputation provider (ipqs, spur or minfraud; minFraud uses "<account id>:<license key>"). Then
     * set `reputation.provider` on PATCH /v1/risk_based_mfa. Lookups are cached and fail open: a provider
     * outage never blocks a sign-in.
     *
     * @param array{secret?:string,provider?:string} $body
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>
     */
    public function setReputationSecret(array $body = [], array $params = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'PUT',
            '/v1/risk_based_mfa/reputation_secret',
            body: $body,
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * `DELETE /v1/risk_based_mfa/reputation_secret` - Remove a stored IP-reputation provider key (default:
     * the configured provider). IP lookups for that provider stop; sign-in is unaffected.
     *
     * @param array{dry_run?:'true'|'false',provider?:string} $params
     *
     * @return array<string,mixed>
     */
    public function removeReputationSecret(array $params = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'DELETE',
            '/v1/risk_based_mfa/reputation_secret',
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }
}
