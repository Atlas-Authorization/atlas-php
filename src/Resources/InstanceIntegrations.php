<?php

declare(strict_types=1);

namespace Atlas\Resources;

/**
 * `/v1/instance/integrations/:id/events_secret/rotate` - 1 operation.
 */
final class InstanceIntegrations extends Resource
{
    /**
     * `POST /v1/instance/integrations/:product/events_secret/rotate` - Rotate the signing secret of the
     * integration's event receiver. The new secret is revealed exactly once. By default (`overlap_seconds`
     * 0) the old secret stops signing at once. With `overlap_seconds` (at most 604800 = 7 days) every
     * delivery carries two signatures in `Atlas-Signature` (`v1,<new> v1,<old>`, the new one first), so
     * the service verifies with either secret while it deploys the new one; after that only the new secret
     * signs. `previous_secret_expires_at` says when the old secret stops signing (epoch ms), or null when
     * it already has. Audited as integration.<id>.events_secret_rotated. 404 when the integration has no
     * event receiver.
     *
     * @param array{overlap_seconds?:int} $body
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>
     */
    public function rotateEventsSecret(string $product, array $body, array $params = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'POST',
            '/v1/instance/integrations/' . rawurlencode($product) . '/events_secret/rotate',
            body: $body,
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }
}
