<?php

declare(strict_types=1);

namespace Atlas\Resources;

/**
 * `/v1/delivery_health/:id/:id` - 1 operation.
 */
final class DeliveryHealth extends Resource
{
    /**
     * `GET /v1/delivery_health/:kind/:id` - Delivery health of one target. 404 until a delivery to it has
     * been recorded.
     *
     * @return array<string,mixed>
     */
    public function get(string $kind, string $id): array
    {
        return $this->http->request('GET', '/v1/delivery_health/' . rawurlencode($kind) . '/' . rawurlencode($id));
    }
}
