<?php

declare(strict_types=1);

namespace Atlas\Webhook;

/**
 * Verify an inbound Atlas webhook, for the customer consuming events.
 *
 * Atlas signs every delivery: `atlas-signature: v1,<base64>` is HMAC-SHA256
 * over `<id>.<timestamp>.<raw body>` keyed with the endpoint secret
 * (`whsec_...`), sent with `atlas-id` and `atlas-timestamp` (epoch ms). A
 * delivery more than five minutes from now, in either direction, is refused.
 * Verify the RAW body, before any JSON decode. Matches `verifyWebhook` in
 * `@atlasauth/backend`.
 *
 *     $result = Webhook::verify(file_get_contents('php://input'), getallheaders(), $secret);
 *     if (!$result->verified) { http_response_code(400); return; }
 *     if ($seen->has($result->deliveryId)) { return; } // a retry already handled
 */
final class Webhook
{
    public const REPLAY_TOLERANCE_MS = 300_000;

    /**
     * @param array<string, string|list<string>> $headers Any casing; `HTTP_ATLAS_ID` style keys are accepted.
     */
    public static function verify(
        string $body,
        array $headers,
        string $secret,
        ?int $nowMs = null,
        int $toleranceMs = self::REPLAY_TOLERANCE_MS,
    ): WebhookVerification {
        $id = self::header($headers, 'atlas-id');
        $tsRaw = self::header($headers, 'atlas-timestamp');
        $signature = self::header($headers, 'atlas-signature');
        if ($id === null || $id === '' || $tsRaw === null || $tsRaw === '' || $signature === null || $signature === '') {
            return new WebhookVerification(false, 'missing_headers');
        }

        $parts = explode(',', $signature, 2);
        if (count($parts) !== 2 || $parts[0] !== 'v1' || $parts[1] === '') {
            return new WebhookVerification(false, 'malformed_signature');
        }

        $tsTrimmed = trim($tsRaw);
        if (preg_match('/^-?\d+$/', $tsTrimmed) !== 1) {
            return new WebhookVerification(false, 'stale_timestamp');
        }
        $timestamp = (int) $tsTrimmed;
        $now = $nowMs ?? (int) floor(microtime(true) * 1000);
        if (abs($now - $timestamp) > $toleranceMs) {
            return new WebhookVerification(false, 'stale_timestamp');
        }

        $expected = base64_encode(hash_hmac('sha256', $id . '.' . $timestamp . '.' . $body, $secret, true));
        if (!hash_equals($expected, $parts[1])) {
            return new WebhookVerification(false, 'signature_mismatch');
        }

        $event = json_decode($body, true);
        return new WebhookVerification(true, null, is_array($event) ? $event : null, $id, $timestamp);
    }

    /**
     * @param array<string, string|list<string>> $headers
     */
    private static function header(array $headers, string $name): ?string
    {
        $rack = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        foreach ($headers as $key => $value) {
            $k = (string) $key;
            if (strcasecmp($k, $name) === 0 || strcasecmp($k, $rack) === 0) {
                if (is_array($value)) {
                    $value = $value[0] ?? null;
                }
                return $value === null ? null : (string) $value;
            }
        }
        return null;
    }
}
