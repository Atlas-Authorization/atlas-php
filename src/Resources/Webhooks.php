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

    /** Delivery log for an endpoint. @return array<string,mixed> */
    public function deliveries(string $id): array
    {
        return $this->http->request('GET', '/v1/webhook_endpoints/' . rawurlencode($id) . '/deliveries');
    }
}
