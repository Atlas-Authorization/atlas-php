<?php

declare(strict_types=1);

namespace Atlas\Resources;

/**
 * `/v1/connections/:id` - 5 operations.
 */
final class ConnectionsWebhooks extends Resource
{
    /**
     * `POST /v1/connections/:id/webhooks/verify` - Verify a provider webhook signature with the
     * connection's secret (the endpoint's own signing secret, else the connection's hmac_secret, else its
     * OAuth client secret). Schemes: hubspot_v3 (needs method and url), shopify_hmac, intercom, zoom,
     * slack_v0, calendly, stripe, generic_hmac_sha256. Constant-time comparison; a signed timestamp must
     * be within the tolerance (5 minutes, Calendly 3); a delivery id or signature already seen on the
     * connection answers `replayed`. Send the body exactly as received (`raw_body`, or base64 with
     * raw_body_encoding). Zoom and Slack handshakes come back in `handshake`. Needs
     * connections:webhook_verify.
     *
     * @param array{scheme:'hubspot_v3'|'shopify_hmac'|'intercom'|'zoom'|'slack_v0'|'calendly'|'stripe'|'generic_hmac_sha256',headers:array<string,mixed>,raw_body:string,raw_body_encoding?:'utf8'|'base64',method?:string,url?:string,endpoint_id?:string,tolerance_seconds?:int,options?:array<string,mixed>,record_replay?:bool} $body
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>
     */
    public function verify(string $id, array $body, array $params = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'POST',
            '/v1/connections/' . rawurlencode($id) . '/webhooks/verify',
            body: $body,
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * `POST /v1/connections/:id/webhook_endpoints` - Create a webhook ingress endpoint on a connection.
     * Point the provider at `ingress_url`; Atlas verifies each delivery, refuses forged, stale and
     * replayed ones, answers Zoom and Slack handshakes, and forwards only genuine deliveries to
     * `forward_url` as Atlas-signed webhooks (atlas-id, atlas-timestamp, atlas-signature with
     * `forward_secret`, returned once). Bodies over 1 MiB are refused; the ingress is rate limited.
     *
     * @param array{scheme:'hubspot_v3'|'shopify_hmac'|'intercom'|'zoom'|'slack_v0'|'calendly'|'stripe'|'generic_hmac_sha256',forward_url:string,signing_secret?:string,options?:array<string,mixed>,enabled?:bool} $body
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>
     */
    public function createEndpoint(string $id, array $body, array $params = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'POST',
            '/v1/connections/' . rawurlencode($id) . '/webhook_endpoints',
            body: $body,
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * `GET /v1/connections/:id/webhook_endpoints` - List a connection's webhook ingress endpoints.
     *
     * @param array{limit?:int,starting_after?:string} $params
     *
     * @return array<string,mixed>
     */
    public function listEndpoints(string $id, array $params = []): array
    {
        return $this->http->request(
            'GET',
            '/v1/connections/' . rawurlencode($id) . '/webhook_endpoints',
            query: $params,
        );
    }

    /**
     * `PATCH /v1/connections/:id/webhook_endpoints/:endpointId` - Change an endpoint's forward_url,
     * enabled flag or signing secret.
     *
     * @param array{scheme?:'hubspot_v3'|'shopify_hmac'|'intercom'|'zoom'|'slack_v0'|'calendly'|'stripe'|'generic_hmac_sha256',forward_url?:string,signing_secret?:string,options?:array<string,mixed>,enabled?:bool} $body
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>
     */
    public function updateEndpoint(string $id, string $endpointId, array $body, array $params = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'PATCH',
            '/v1/connections/' . rawurlencode($id) . '/webhook_endpoints/' . rawurlencode($endpointId),
            body: $body,
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * `DELETE /v1/connections/:id/webhook_endpoints/:endpointId` - Delete a webhook ingress endpoint and
     * its secrets.
     *
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>|null
     */
    public function deleteEndpoint(string $id, string $endpointId, array $params = [], ?string $idempotencyKey = null): ?array
    {
        return $this->http->request(
            'DELETE',
            '/v1/connections/' . rawurlencode($id) . '/webhook_endpoints/' . rawurlencode($endpointId),
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }
}
