<?php

declare(strict_types=1);

namespace Atlas\PolicySource;

/**
 * The outcome of {@see PolicySourceProtocol::verifyFeedRequest()}. Never thrown: check `$verified`.
 * `$reason` (failures only) is one of `wrong_method`, `missing_headers`, `malformed_signature`,
 * `stale_timestamp`, `signature_mismatch`. On success `$deliveryId` is the signed `atlas-id`,
 * `$timestamp` the signed `atlas-timestamp` (epoch ms), `$key` the `key` query parameter when
 * Atlas asks for one policy (else null) and `$protocolVersion` the `atlas-policies-version`
 * header (not signed).
 */
final class PolicyFeedVerification
{
    public function __construct(
        public readonly bool $verified,
        public readonly ?string $reason = null,
        public readonly ?string $deliveryId = null,
        public readonly ?int $timestamp = null,
        public readonly ?string $key = null,
        public readonly ?string $protocolVersion = null,
    ) {
    }
}
