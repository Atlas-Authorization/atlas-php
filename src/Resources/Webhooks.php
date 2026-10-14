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
     * @param array{limit?:int} $params `limit` defaults to 50, max 100
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
}
