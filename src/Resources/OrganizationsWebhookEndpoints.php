<?php

declare(strict_types=1);

namespace Atlas\Resources;

/**
 * `/v1/organizations/:id/webhook_endpoints` - 7 operations.
 */
final class OrganizationsWebhookEndpoints extends Resource
{
    /**
     * `GET /v1/organizations/:id/webhook_endpoints` - List the organization's own webhook endpoints.
     * Backend API. Never returns a secret.
     *
     * @param array{limit?:int,starting_after?:string} $params
     *
     * @return array<string,mixed>
     */
    public function list(string $id, array $params = []): array
    {
        return $this->http->request(
            'GET',
            '/v1/organizations/' . rawurlencode($id) . '/webhook_endpoints',
            query: $params,
        );
    }

    /**
     * `POST /v1/organizations/:id/webhook_endpoints` - Register a webhook endpoint for one organization.
     * It receives only that organization's events (membership, invitation, role, domain and organization
     * events). The signing secret is returned in this response only. Backend API. At most 20 per
     * organization.
     *
     * @param array{url?:string,enabled_events?:list<string>,description?:string|null,active?:bool} $body
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>
     */
    public function create(string $id, array $body, array $params = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'POST',
            '/v1/organizations/' . rawurlencode($id) . '/webhook_endpoints',
            body: $body,
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * `GET /v1/organizations/:id/webhook_endpoints/:endpointId` - Retrieve one of the organization's
     * webhook endpoints, with its delivery health.
     *
     * @return array<string,mixed>
     */
    public function get(string $id, string $endpointId): array
    {
        return $this->http->request(
            'GET',
            '/v1/organizations/' . rawurlencode($id) . '/webhook_endpoints/' . rawurlencode($endpointId),
        );
    }

    /**
     * `PATCH /v1/organizations/:id/webhook_endpoints/:endpointId` - Update the url, subscribed events,
     * description or active flag of one of the organization's endpoints. Never re-reveals the secret.
     *
     * @param array{url?:string,enabled_events?:list<string>,description?:string|null,active?:bool} $body
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>
     */
    public function update(string $id, string $endpointId, array $body, array $params = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'PATCH',
            '/v1/organizations/' . rawurlencode($id) . '/webhook_endpoints/' . rawurlencode($endpointId),
            body: $body,
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * `DELETE /v1/organizations/:id/webhook_endpoints/:endpointId` - Delete one of the organization's
     * webhook endpoints and its delivery log. An id from another organization is a 404.
     *
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>
     */
    public function delete(string $id, string $endpointId, array $params = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'DELETE',
            '/v1/organizations/' . rawurlencode($id) . '/webhook_endpoints/' . rawurlencode($endpointId),
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * `POST /v1/organizations/:id/webhook_endpoints/:endpointId/rotate_secret` - Rotate the signing
     * secret. The previous secret keeps signing alongside the new one for 24 hours so the receiver can
     * switch over without dropping events.
     *
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>
     */
    public function rotateSecret(string $id, string $endpointId, array $params = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'POST',
            '/v1/organizations/' . rawurlencode($id) . '/webhook_endpoints/' . rawurlencode($endpointId) . '/rotate_secret',
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * `GET /v1/organizations/:id/webhook_endpoints/:endpointId/deliveries` - The endpoint's delivery log:
     * payload, response and retry state for each attempt, filterable by status, event type and time.
     *
     * @param array{limit?:int,starting_after?:string,status?:'pending'|'success'|'failed',event_type?:string,since?:string,until?:string,trace?:string} $params
     *
     * @return array<string,mixed>
     */
    public function deliveries(string $id, string $endpointId, array $params = []): array
    {
        return $this->http->request(
            'GET',
            '/v1/organizations/' . rawurlencode($id) . '/webhook_endpoints/' . rawurlencode($endpointId) . '/deliveries',
            query: $params,
        );
    }
}
