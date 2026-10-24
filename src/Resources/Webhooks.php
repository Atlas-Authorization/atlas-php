<?php

declare(strict_types=1);

namespace Atlas\Resources;

use Atlas\Http;

/** `/v1/webhook_endpoints` — webhook endpoints and their delivery logs. */
final class Webhooks extends Resource
{
    public readonly WebhookEndpoints $endpoints;

    public function __construct(Http $http)
    {
        parent::__construct($http);
        $this->endpoints = new WebhookEndpoints($http);
    }

    /**
     * Delivery log for an endpoint, newest first.
     *
     * @param array{limit?:int,status?:string,event_type?:string,since?:int|string,until?:int|string,starting_after?:string,trace?:string} $params
     *   `limit` defaults to 50, max 1000; entries carry `latency_ms` and `trace_id`; cursor-paginated (`next_cursor`)
     *
     * @return array<string,mixed>
     */
    public function deliveries(string $id, array $params = []): array
    {
        return $this->http->request('GET', '/v1/webhook_endpoints/' . rawurlencode($id) . '/deliveries', query: $params);
    }

    /**
     * `POST /v1/webhook_endpoints/:id/deliveries/:deliveryId/redeliver` — redeliver a past event as a
     * fresh attempt.
     *
     * @return array<string,mixed>
     */
    public function redeliver(string $endpointId, string $deliveryId): array
    {
        return $this->http->request(
            'POST',
            '/v1/webhook_endpoints/' . rawurlencode($endpointId) . '/deliveries/' . rawurlencode($deliveryId) . '/redeliver',
        );
    }

    /**
     * `GET /v1/meta/egress_ips` — the addresses deliveries leave from (allow-list them). Public.
     *
     * @return array<string,mixed>
     */
    public function egressIps(): array
    {
        return $this->http->request('GET', '/v1/meta/egress_ips');
    }

    /**
     * `GET /v1/delivery_health` — delivery health of every outbound target (webhooks, referral connection, back-channel receivers, integrations). `kind`, `state` (`unhealthy`|`all`).
     *
     * @return array<string,mixed>
     */
    public function health(array $params = []): array
    {
        return $this->http->request('GET', '/v1/delivery_health', query: $params);
    }

    /**
     * `PUT /v1/delivery_health/:kind/:targetId/alert_contacts` — who is emailed when a target fails (`null` = the account's owners).
     *
     * @return array<string,mixed>
     */
    public function setAlertContacts(string $kind, string $targetId, ?array $alertContacts): array
    {
        return $this->http->request('PUT', '/v1/delivery_health/' . rawurlencode($kind) . '/' . rawurlencode($targetId) . '/alert_contacts', body: ['alert_contacts' => $alertContacts]);
    }
}
