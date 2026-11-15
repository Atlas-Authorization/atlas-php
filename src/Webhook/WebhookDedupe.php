<?php

declare(strict_types=1);

namespace Atlas\Webhook;

use Atlas\Exception\ConfigException;

/**
 * Verify an Atlas webhook ({@see Webhook::verify()}) and drop a delivery whose `atlas-id` was already seen. An unverified delivery never
 * touches the store.
 *
 *     $delivery = WebhookDedupe::verify($rawBody, getallheaders(), $secret, new MemoryWebhookDedupeStore());
 *     if (!$delivery->duplicate) { try { handle($delivery->event); } catch (\Throwable $e) { $delivery->release(); throw $e; } }
 *     http_response_code(200);
 */
final class WebhookDedupe
{
    /**
     * @param array<string,string|list<string>> $headers
     * @param int                               $ttlSeconds how long a delivery id is remembered (keep it longer than your endpoint's retry window)
     *
     * @throws WebhookVerificationException when the delivery does not verify (answer 400/401)
     */
    public static function verify(
        string $body,
        array $headers,
        string $secret,
        WebhookDedupeStore $store,
        int $ttlSeconds = 86_400,
        string $keyPrefix = 'atlas-webhook:',
        ?string $url = null,
        bool $requireV2 = false,
    ): WebhookDelivery {
        $result = Webhook::verify($body, $headers, $secret, url: $url, requireV2: $requireV2);
        if (!$result->verified || $result->deliveryId === null) {
            throw new WebhookVerificationException($result->reason ?? 'signature_mismatch');
        }
        if ($result->event === null) {
            throw new WebhookVerificationException('invalid_body');
        }
        if ($ttlSeconds < 1) {
            throw new ConfigException('ttlSeconds must be at least 1.');
        }
        $key = $keyPrefix . $result->deliveryId;

        return new WebhookDelivery($result->event, !$store->setIfAbsent($key, $ttlSeconds), $result->deliveryId, $store, $key);
    }
}
