<?php

declare(strict_types=1);

namespace Atlas\Resources;

/**
 * `/v1/session_exchange_apps` — downstream apps notified by back-channel logout when an Atlas session ends.
 */
final class SessionExchangeApps extends Resource
{
    /**
     * `GET /v1/session_exchange_apps` — registered session-exchange apps and their back-channel logout
     * endpoints.
     *
     * @return array<string,mixed>
     */
    public function list(): array
    {
        return $this->http->request('GET', '/v1/session_exchange_apps');
    }

    /**
     * `POST /v1/session_exchange_apps` — register a session-exchange app (name, backchannel_logout_uri,
     * audience).
     *
     * @param array<string,mixed> $body
     *
     * @return array<string,mixed>
     */
    public function create(array $body, ?string $idempotencyKey = null): array
    {
        return $this->http->request('POST', '/v1/session_exchange_apps', body: $body, idempotencyKey: $idempotencyKey);
    }

    /**
     * `GET /v1/session_exchange_apps/:id` — one session-exchange app.
     *
     * @return array<string,mixed>
     */
    public function get(string $id): array
    {
        return $this->http->request('GET', '/v1/session_exchange_apps/' . rawurlencode($id));
    }

    /**
     * `PATCH /v1/session_exchange_apps/:id` — update a session-exchange app.
     *
     * @param array<string,mixed> $body
     *
     * @return array<string,mixed>
     */
    public function update(string $id, array $body, ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'PATCH',
            '/v1/session_exchange_apps/' . rawurlencode($id),
            body: $body,
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * `DELETE /v1/session_exchange_apps/:id` — delete a session-exchange app.
     *
     * @return array<string,mixed>
     */
    public function delete(string $id, ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'DELETE',
            '/v1/session_exchange_apps/' . rawurlencode($id),
            idempotencyKey: $idempotencyKey,
        );
    }
}
