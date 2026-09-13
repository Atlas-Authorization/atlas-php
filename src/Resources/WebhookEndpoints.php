<?php

declare(strict_types=1);

namespace Atlas\Resources;

/** `/v1/webhook_endpoints` — CRUD for webhook endpoints (signing secret revealed once). */
final class WebhookEndpoints extends Resource
{
    /** @return array<string,mixed> */
    public function list(): array
    {
        return $this->http->request('GET', '/v1/webhook_endpoints');
    }

    /**
     * @param array{url:string,enabled_events?:list<string>} $body
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
}
