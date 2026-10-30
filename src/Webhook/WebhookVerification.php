<?php

declare(strict_types=1);

namespace Atlas\Webhook;

/**
 * The outcome of {@see Webhook::verify()}. Never thrown: check `$verified`.
 * `$deliveryId` (the signed `atlas-id`, the same on every retry of one event:
 * deduplicate on it) and `$timestamp` (epoch ms; seconds from {@see Webhook::verifyStandard()}) are set only when verified.
 * `$reason` is one of `missing_headers`, `malformed_signature`,
 * `stale_timestamp`, `signature_mismatch`.
 */
final class WebhookVerification
{
    /**
     * @param array<string, mixed>|null $event
     */
    public function __construct(
        public readonly bool $verified,
        public readonly ?string $reason = null,
        public readonly ?array $event = null,
        public readonly ?string $deliveryId = null,
        public readonly ?int $timestamp = null,
    ) {
    }
}
