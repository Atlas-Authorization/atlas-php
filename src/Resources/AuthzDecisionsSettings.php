<?php

declare(strict_types=1);

namespace Atlas\Resources;

/**
 * `/v1/authz_decision_settings` - 2 operations.
 */
final class AuthzDecisionsSettings extends Resource
{
    /**
     * `GET /v1/authz_decision_settings` - How authorization decisions are captured: on or off, sampling
     * per outcome, how much of each request is kept, redaction paths, event shipping and retention.
     *
     * @return array<string,mixed>
     */
    public function get(): array
    {
        return $this->http->request('GET', '/v1/authz_decision_settings');
    }

    /**
     * `PUT /v1/authz_decision_settings` - Change how decisions are captured. Fields you omit keep their
     * value. Capture is off by default; `input_mode` defaults to `ids` (identifiers only) so nothing
     * personal is stored until you ask for it. With `ship_to_events` each recorded decision is also an
     * `authz.decision` event for your log streams and webhooks.
     *
     * @param array{enabled?:bool,sample_rate_allow?:int|float,sample_rate_deny?:int|float,input_mode?:'ids'|'full'|'none',redact?:list<string>,ship_to_events?:bool,retention_days?:int|null} $body
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>
     */
    public function update(array $body = [], array $params = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'PUT',
            '/v1/authz_decision_settings',
            body: $body,
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }
}
