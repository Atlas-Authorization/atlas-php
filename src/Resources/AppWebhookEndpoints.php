<?php

declare(strict_types=1);

namespace Atlas\Resources;

/**
 * `/v1/organizations/:id/app_webhook_endpoints` — one organization's endpoints for your
 * application's events. Endpoints, deliveries and replay results have the same shapes as the
 * instance webhook endpoints ({@see WebhookEndpoints}, {@see Webhooks}).
 */
final class AppWebhookEndpoints extends Resource
{
    /** @return array<string,mixed> a ListPage of webhook endpoints */
    public function list(string $orgId): array
    {
        return $this->http->request('GET', self::base($orgId));
    }

    /**
     * The `whsec_` signing secret is in this response only (or {@see revealSecret()}).
     *
     * @param array{url:string,enabled_events?:list<string>,description?:string|null,active?:bool} $body
     *   `enabled_events`: types from your catalog, or `['*']` (the default) for all of them
     *
     * @return array<string,mixed>
     */
    public function create(string $orgId, array $body, ?string $idempotencyKey = null): array
    {
        return $this->http->request('POST', self::base($orgId), body: $body, idempotencyKey: $idempotencyKey);
    }

    /** @return array<string,mixed> */
    public function get(string $orgId, string $endpointId): array
    {
        return $this->http->request('GET', self::one($orgId, $endpointId));
    }

    /**
     * @param array{url?:string,enabled_events?:list<string>,description?:string|null,active?:bool} $body
     *
     * @return array<string,mixed>
     */
    public function update(string $orgId, string $endpointId, array $body): array
    {
        return $this->http->request('PATCH', self::one($orgId, $endpointId), body: $body);
    }

    /** @return array<string,mixed> `{object: 'webhook_endpoint', id, deleted: true}` */
    public function delete(string $orgId, string $endpointId): array
    {
        return $this->http->request('DELETE', self::one($orgId, $endpointId));
    }

    /**
     * The previous secret keeps signing for 24 hours.
     *
     * @return array<string,mixed> `{object: 'webhook_secret', secret, overlap_seconds, previous_secret_expires_at}`
     */
    public function rotateSecret(string $orgId, string $endpointId): array
    {
        return $this->http->request('POST', self::one($orgId, $endpointId) . '/rotate_secret');
    }

    /** @return array<string,mixed> `{object: 'webhook_secret', secret}` */
    public function revealSecret(string $orgId, string $endpointId): array
    {
        return $this->http->request('POST', self::one($orgId, $endpointId) . '/reveal_secret');
    }

    /**
     * Send a test event. `$wait` (e.g. `'10s'`) attempts it now and returns the result; without it
     * the delivery is queued.
     *
     * @return array<string,mixed>
     */
    public function test(string $orgId, string $endpointId, ?string $wait = null): array
    {
        return $this->http->request('POST', self::one($orgId, $endpointId) . '/test', query: ['wait' => $wait]);
    }

    /**
     * The endpoint's delivery log.
     *
     * @param array{limit?:int,starting_after?:string,status?:'pending'|'success'|'failed',event_type?:string,since?:string,until?:string,trace?:string} $params
     *
     * @return array<string,mixed> a ListPage of webhook deliveries
     */
    public function deliveries(string $orgId, string $endpointId, array $params = []): array
    {
        return $this->http->request('GET', self::one($orgId, $endpointId) . '/deliveries', query: $params);
    }

    /** @return array<string,mixed> `{object: 'webhook_delivery', id, event_id, status, note}` */
    public function redeliver(string $orgId, string $endpointId, string $deliveryId): array
    {
        return $this->http->request(
            'POST',
            self::one($orgId, $endpointId) . '/deliveries/' . rawurlencode($deliveryId) . '/redeliver',
        );
    }

    /**
     * Re-send what the endpoint missed since `since` (epoch, or `'failing_since'`).
     *
     * @param array{since?:int|string,until?:int|string,event_type?:string} $body
     *
     * @return array<string,mixed>
     */
    public function replay(string $orgId, string $endpointId, array $body = []): array
    {
        return $this->http->request('POST', self::one($orgId, $endpointId) . '/replay', body: $body);
    }

    /**
     * `GET /v1/organizations/:id/app_event_types` — the event types this organization can subscribe its
     * endpoints to (the picker list; deprecated types left out).
     *
     * @return array<string,mixed> a ListPage of AppEventType
     */
    public function eventTypes(string $orgId): array
    {
        return $this->http->request('GET', '/v1/organizations/' . rawurlencode($orgId) . '/app_event_types');
    }

    private static function base(string $orgId): string
    {
        return '/v1/organizations/' . rawurlencode($orgId) . '/app_webhook_endpoints';
    }

    private static function one(string $orgId, string $endpointId): string
    {
        return self::base($orgId) . '/' . rawurlencode($endpointId);
    }
}
