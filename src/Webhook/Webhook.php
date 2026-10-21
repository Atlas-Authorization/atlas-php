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
 * The header may carry several space-separated `v1,<base64>` entries (one per
 * secret while a secret rotates with an overlap); the delivery verifies when
 * any one matches. Verify the RAW body, before any JSON decode. Matches `verifyWebhook` in
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

        $provided = self::signatures($signature);
        if ($provided === []) {
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
        // Compare every entry (no early exit), each in constant time.
        $matched = false;
        foreach ($provided as $candidate) {
            if (hash_equals($expected, $candidate)) {
                $matched = true;
            }
        }
        if (!$matched) {
            return new WebhookVerification(false, 'signature_mismatch');
        }

        $event = json_decode($body, true);
        return new WebhookVerification(true, null, is_array($event) ? $event : null, $id, $timestamp);
    }

    /**
     * The `v1` signatures in an `atlas-signature` header: one `v1,<base64>` entry, or several
     * separated by spaces (while an endpoint secret rotates, Atlas signs with the old and the new
     * secret). Entries of another version are skipped.
     *
     * @return list<string>
     */
    private static function signatures(string $header): array
    {
        $out = [];
        foreach (preg_split('/\s+/', trim($header), -1, PREG_SPLIT_NO_EMPTY) ?: [] as $entry) {
            $parts = explode(',', $entry, 2);
            if (count($parts) === 2 && $parts[0] === 'v1' && $parts[1] !== '') {
                $out[] = $parts[1];
            }
        }
        return $out;
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
