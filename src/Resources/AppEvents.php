<?php

declare(strict_types=1);

namespace Atlas\Resources;

use Atlas\Http;

/**
 * Application webhooks: send YOUR app's own events (`call.completed`, `invoice.paid`) to endpoints
 * your customers (organizations) register. Atlas signs them (Standard Webhooks — verify with
 * {@see \Atlas\Webhook\Webhook::verifyStandard()}), retries, tracks health, auto-disables a dead
 * endpoint and keeps the delivery log with redeliver and replay.
 *
 *     $atlas->appEvents->types->create(['type' => 'call.completed', 'description' => 'A call finished.']);
 *     $atlas->appEvents->publish(['organization_id' => $orgId, 'type' => 'call.completed', 'data' => ['call_id' => $id], 'idempotency_key' => $id]);
 *
 * An AppEvent is `{object: 'app_event', id, type, organization_id, data, idempotency_key, created_at}`.
 */
final class AppEvents extends Resource
{
    /** Your event catalog (`/v1/app_event_types`). */
    public readonly AppEventTypes $types;
    /** One organization's endpoints for your events (`/v1/organizations/:id/app_webhook_endpoints`). */
    public readonly AppWebhookEndpoints $endpoints;

    public function __construct(Http $http)
    {
        parent::__construct($http);
        $this->types = new AppEventTypes($http);
        $this->endpoints = new AppWebhookEndpoints($http);
    }

    /**
     * `POST /v1/app_events` — publish one event to one organization's subscribed endpoints. A repeat
     * with the same `idempotency_key` (kept 90 days) returns the first event with
     * `idempotent_replay: true` and queues nothing; the first publish carries `deliveries_queued` and
     * `endpoint_ids`.
     *
     * @param array{organization_id?:string,org?:string,type:string,data:array<string,mixed>,idempotency_key?:string} $body
     *
     * @return array<string,mixed> the PublishedAppEvent
     */
    public function publish(array $body, ?string $idempotencyKey = null): array
    {
        return $this->http->request('POST', '/v1/app_events', body: $body, idempotencyKey: $idempotencyKey);
    }

    /**
     * `GET /v1/app_events` — one organization's published events, newest first.
     *
     * @param array{organization_id:string,type?:string,limit?:int,starting_after?:string} $params
     *
     * @return array<string,mixed> a ListPage of AppEvent
     */
    public function list(array $params): array
    {
        return $this->http->request('GET', '/v1/app_events', query: $params);
    }

    /** @return array<string,mixed> the AppEvent */
    public function get(string $eventId): array
    {
        return $this->http->request('GET', '/v1/app_events/' . rawurlencode($eventId));
    }
}
