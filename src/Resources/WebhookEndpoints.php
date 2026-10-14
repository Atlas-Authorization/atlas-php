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
     * once.
     *
     * @return array<string,mixed>
     */
    public function rotateSecret(string $id): array
    {
        return $this->http->request('POST', '/v1/webhook_endpoints/' . rawurlencode($id) . '/rotate_secret');
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
}
