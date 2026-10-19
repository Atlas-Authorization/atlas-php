<?php

declare(strict_types=1);

namespace Atlas\Resources;

/**
 * `/v1/logout_notify_endpoints` — logout fan-out ("also notify") endpoints: downstream services
 * that receive the same signed `logout_token` as your OIDC apps whenever an Atlas session ends,
 * plus a subject-wide one (`sub`, no `sid`) when a user is banned, deleted or deprovisioned.
 *
 * @phpstan-type LogoutNotifyEndpoint array{object:'logout_notify_endpoint',id:string,url:string,oauth_client_id:string|null,audience:string,description?:string|null,created_at:int,updated_at:int}
 */
final class LogoutNotifyEndpoints extends Resource
{
    /** @return array{object:string,data:list<LogoutNotifyEndpoint>,has_more?:bool} */
    public function list(): array
    {
        return $this->http->request('GET', '/v1/logout_notify_endpoints');
    }

    /**
     * Register an endpoint (public https). With `oauth_client_id` it follows one client.
     *
     * @param array{url:string,oauth_client_id?:string|null,audience?:string|null,description?:string|null} $body
     *
     * @return LogoutNotifyEndpoint
     */
    public function create(array $body, ?string $idempotencyKey = null): array
    {
        return $this->http->request('POST', '/v1/logout_notify_endpoints', body: $body, idempotencyKey: $idempotencyKey);
    }

    /** @return LogoutNotifyEndpoint */
    public function get(string $id): array
    {
        return $this->http->request('GET', '/v1/logout_notify_endpoints/' . rawurlencode($id));
    }

    /**
     * @param array{url?:string,audience?:string|null,description?:string|null} $body
     *
     * @return LogoutNotifyEndpoint
     */
    public function update(string $id, array $body, ?string $idempotencyKey = null): array
    {
        return $this->http->request('PATCH', '/v1/logout_notify_endpoints/' . rawurlencode($id), body: $body, idempotencyKey: $idempotencyKey);
    }

    /**
     * Delete an endpoint; its queued deliveries go with it.
     *
     * @return array{object:string,id:string,deleted:bool}
     */
    public function delete(string $id, ?string $idempotencyKey = null): array
    {
        return $this->http->request('DELETE', '/v1/logout_notify_endpoints/' . rawurlencode($id), idempotencyKey: $idempotencyKey);
    }

    /**
     * The delivery log, newest first: `sub`, `sid` (null for a subject-wide logout), `status`,
     * `http_status` and `next_retry_at`.
     *
     * @param array{limit?:int} $params
     *
     * @return array{object:string,data:list<array<string,mixed>>,has_more?:bool}
     */
    public function listDeliveries(string $id, array $params = []): array
    {
        return $this->http->request('GET', '/v1/logout_notify_endpoints/' . rawurlencode($id) . '/deliveries', query: $params);
    }
}
