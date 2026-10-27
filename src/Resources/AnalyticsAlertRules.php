<?php

declare(strict_types=1);

namespace Atlas\Resources;

/**
 * `/v1/analytics/alert_rules` - 6 operations.
 */
final class AnalyticsAlertRules extends Resource
{
    /**
     * `GET /v1/analytics/alert_rules` - List analytics alert rules with their state and last measured
     * value. The metric catalog is included.
     *
     * @param array{limit?:int,starting_after?:string} $params
     *
     * @return array<string,mixed>
     */
    public function list(array $params = []): array
    {
        return $this->http->request('GET', '/v1/analytics/alert_rules', query: $params);
    }

    /**
     * `POST /v1/analytics/alert_rules` - Create an alert rule: tell me when a sign-in health metric, or a
     * key or organization's API usage against its baseline, crosses a threshold over a trailing window. It
     * must have email_contacts, a webhook_url or ssf. A webhook signing secret is returned once.
     *
     * @param array{name:string,metric:'sign_in_success_rate'|'failed_passkey_ratio'|'failed_sign_ins'|'sign_in_p95_seconds'|'api_usage_spike',comparator:'lt'|'gt',threshold:int|float,window_minutes?:int,min_sample?:int,cooldown_minutes?:int,email_contacts?:list<string>,webhook_url?:string|null,enabled?:bool,subject_type?:string|null,subject_id?:string|null,ssf?:bool} $body
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>
     */
    public function create(array $body, array $params = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'POST',
            '/v1/analytics/alert_rules',
            body: $body,
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * `GET /v1/analytics/alert_rules/:id` - One analytics alert rule.
     *
     * @return array<string,mixed>
     */
    public function get(string $id): array
    {
        return $this->http->request('GET', '/v1/analytics/alert_rules/' . rawurlencode($id));
    }

    /**
     * `PATCH /v1/analytics/alert_rules/:id` - Edit an alert rule. Editing re-arms it.
     *
     * @param array{name?:string,metric?:'sign_in_success_rate'|'failed_passkey_ratio'|'failed_sign_ins'|'sign_in_p95_seconds'|'api_usage_spike',comparator?:'lt'|'gt',threshold?:int|float,window_minutes?:int,min_sample?:int,cooldown_minutes?:int,email_contacts?:list<string>,webhook_url?:string|null,enabled?:bool,subject_type?:string|null,subject_id?:string|null,ssf?:bool} $body
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>
     */
    public function update(string $id, array $body, array $params = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'PATCH',
            '/v1/analytics/alert_rules/' . rawurlencode($id),
            body: $body,
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * `DELETE /v1/analytics/alert_rules/:id` - Delete an alert rule.
     *
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>
     */
    public function delete(string $id, array $params = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'DELETE',
            '/v1/analytics/alert_rules/' . rawurlencode($id),
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * `POST /v1/analytics/alert_rules/:id/test` - Measure the rule now. With `notify: true` it also sends
     * the rule's email and webhook, whether or not the rule is breaching, to prove the delivery path.
     *
     * @param array{notify?:bool} $body
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>
     */
    public function test(string $id, array $body, array $params = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'POST',
            '/v1/analytics/alert_rules/' . rawurlencode($id) . '/test',
            body: $body,
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }
}
