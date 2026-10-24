<?php

declare(strict_types=1);

namespace Atlas\Resources;

/** `/v1/webhook_endpoints` — CRUD for webhook endpoints (signing secret revealed once). */
final class WebhookEndpoints extends Resource
{
    /**
     * @param array{include_deprecated?:bool,metadata?:array<string,string>} $params `include_deprecated` lists endpoints
     *   still subscribed to deprecated event names; `metadata[key]=value` filters
     *
     * @return array<string,mixed>
     */
    public function list(array $params = []): array
    {
        return $this->http->request('GET', '/v1/webhook_endpoints', query: $params);
    }

    /**
     * @param array{url:string,enabled_events?:list<string>,description?:string,event_naming?:string,signature_versions?:list<string>,external?:bool,alert_contacts?:list<string>,destination?:array<string,mixed>,destination_credentials?:array<string,mixed>,metadata?:array<string,mixed>,external_ref?:string} $body
     *
     * @return array<string,mixed> the endpoint plus its once-only `whsec_` secret
     */
    public function create(array $body, ?string $idempotencyKey = null): array
    {
        return $this->http->request('POST', '/v1/webhook_endpoints', body: $body, idempotencyKey: $idempotencyKey);
    }

    /** @return array<string,mixed> */
    public function delete(string $id): array
    {
        return $this->http->request('DELETE', '/v1/webhook_endpoints/' . rawurlencode($id));
    }

    /**
     * `PATCH /v1/webhook_endpoints/:id` — update an endpoint's url, enabled_events or active flag. The
     * signing secret is kept.
     *
     * @param array<string,mixed> $body
     *
     * @return array<string,mixed>
     */
    public function update(string $id, array $body): array
    {
        return $this->http->request('PATCH', '/v1/webhook_endpoints/' . rawurlencode($id), body: $body);
    }

    /**
     * `POST /v1/webhook_endpoints/:id/reveal_secret` — show the endpoint's current signing secret again.
     * Does not change it.
     *
     * @return array<string,mixed>
     */
    public function revealSecret(string $id): array
    {
        return $this->http->request('POST', '/v1/webhook_endpoints/' . rawurlencode($id) . '/reveal_secret');
    }

    /**
     * `POST /v1/webhook_endpoints/:id/rotate_secret` — rotate the signing secret. The new secret is shown
     * once. By default the old secret stops signing at once. With `overlap_seconds` (at most 604800) every
     * delivery is signed with both until `previous_secret_expires_at` (epoch ms), so a verifier holding
     * either passes. Choose an overlap only once every receiver accepts several signatures (0.7.0+).
     *
     * @param array{overlap_seconds?:int}|null $body
     *
     * @return array<string,mixed>
     */
    public function rotateSecret(string $id, ?array $body = null): array
    {
        return $this->http->request('POST', '/v1/webhook_endpoints/' . rawurlencode($id) . '/rotate_secret', body: $body);
    }

    /**
     * `POST /v1/webhook_endpoints/:id/test` — send a synthetic webhook.test event to this endpoint only.
     *
     * @return array<string,mixed>
     */
    public function test(string $id): array
    {
        return $this->http->request('POST', '/v1/webhook_endpoints/' . rawurlencode($id) . '/test');
    }

    /**
     * `GET /v1/webhook_endpoints/:id` — one endpoint, with its health (`health`, `last_success_at`, `last_failure`, `consecutive_failures`, `success_rate_24h`, `attempts_24h`, `alert_contacts`, `auto_disable`, `replay`), `event_naming`, `signature_versions`, `destination`.
     *
     * @return array<string,mixed>
     */
    public function get(string $id): array
    {
        return $this->http->request('GET', '/v1/webhook_endpoints/' . rawurlencode($id));
    }

    /**
     * `PUT /v1/webhook_endpoints/by_url` — find-or-create by url: updates the endpoint with this url, or creates it (then `secret` is in the answer, once). Safe to run on every deploy. Body: `{url, enabled_events?, description?, event_naming?: canonical|legacy, signature_versions?, external?, alert_contacts?, destination?, destination_credentials?}`.
     *
     * @return array<string,mixed>
     */
    public function upsertByUrl(array $body, ?string $idempotencyKey = null): array
    {
        return $this->http->request('PUT', '/v1/webhook_endpoints/by_url', body: $body, idempotencyKey: $idempotencyKey);
    }

    /**
     * `POST /v1/webhook_endpoints/:id/test?wait=` — send `webhook.test` now and get the result inline (status, HTTP status, latency, response snippet, error, the signature header sent). `$wait` is at most 10 s (`'10s'`, `'5000ms'`).
     *
     * @return array<string,mixed>
     */
    public function testNow(string $id, string $wait = '10s'): array
    {
        return $this->http->request('POST', '/v1/webhook_endpoints/' . rawurlencode($id) . '/test', query: ['wait' => $wait]);
    }

    /**
     * `POST /v1/webhook_endpoints/:id/redeliver` — bulk redeliver every event whose latest attempt in the window has `status` (default `failed`). Body: `{since?, until?, status?, event_type?, limit?}`; `since` is epoch ms or `'failing_since'` ({@see \Atlas\Constants::WEBHOOK_REPLAY_SINCE_FAILING}), the start of the endpoint's last failure streak.
     *
     * @return array<string,mixed>
     */
    public function redeliverWindow(string $id, array $body = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request('POST', '/v1/webhook_endpoints/' . rawurlencode($id) . '/redeliver', body: $body, idempotencyKey: $idempotencyKey);
    }

    /**
     * `POST /v1/webhook_endpoints/:id/replay` — re-send everything since `since` that the endpoint subscribes to and has not received. Body: `{since, until?, event_type?, limit?}`. `since: 'failing_since'` replays everything since the endpoint started failing; it resolves from the live `failing_since`, else `disabled_at`, else `last_failing_since` (kept after the endpoint recovers or is re-enabled), and is a 400 when the endpoint never failed.
     *
     * @return array<string,mixed>
     */
    public function replay(string $id, array $body, ?string $idempotencyKey = null): array
    {
        return $this->http->request('POST', '/v1/webhook_endpoints/' . rawurlencode($id) . '/replay', body: $body, idempotencyKey: $idempotencyKey);
    }
}
