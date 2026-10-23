<?php

declare(strict_types=1);

namespace Atlas\PolicySource;

/**
 * Atlas policy source protocol v1 — helpers for the system that SERVES an Atlas instance's
 * policy documents (a CMS, a docs platform, your own service).
 *
 *  - Atlas to you: `GET {endpoint_url}/policies` (the full feed) and
 *    `GET {endpoint_url}/policies?key=<key>` (one policy, after your event), signed with the
 *    source's signing secret: `atlas-id`, `atlas-timestamp` (epoch ms) and
 *    `atlas-signature: v1,<base64 HMAC-SHA256>` over `{atlas-id}.{atlas-timestamp}.GET\n{path+query}`.
 *    Check it with {@see verifyFeedRequest()}, then answer a feed
 *    (`['object' => 'policy_list', 'policies' => [...]]`).
 *  - You to Atlas: `POST {webhook_url}` (from `$atlas->policySource->get()`), a `policy.published`
 *    / `policy.retired` event signed with the same secret by {@see signEvent()}. Atlas then
 *    re-fetches that policy; it never stores content from the event.
 *
 * Matches `verifyPolicyFeedRequest` / `signPolicySourceEvent` in `@atlasauth/backend`.
 *
 *     $v = PolicySourceProtocol::verifyFeedRequest($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI'], getallheaders(), $secret);
 *     if (!$v->verified) { http_response_code(401); return; }
 *     echo json_encode(buildFeed($v->key)); // one policy when $v->key is set
 */
final class PolicySourceProtocol
{
    public const VERSION = '2026-10-24';

    public const VERSION_HEADER = 'atlas-policies-version';

    public const TOLERANCE_MS = 300_000;

    /**
     * Verify Atlas's signed GET of your feed. Pass the request method, its path AND query exactly
     * as received (e.g. `$_SERVER['REQUEST_URI']`), the headers and the source's signing secret.
     * The `atlas-signature` header may carry several `v1,<base64>` values; one match is enough. A
     * timestamp more than five minutes from now is refused.
     *
     * @param array<string, string|list<string>> $headers Any casing; `HTTP_ATLAS_ID` style keys are accepted.
     */
    public static function verifyFeedRequest(
        ?string $method,
        string $pathAndQuery,
        array $headers,
        string $secret,
        ?int $nowMs = null,
        int $toleranceMs = self::TOLERANCE_MS,
    ): PolicyFeedVerification {
        if ($method !== null && $method !== '' && strtoupper($method) !== 'GET') {
            return new PolicyFeedVerification(false, 'wrong_method');
        }
        $id = self::header($headers, 'atlas-id');
        $tsRaw = self::header($headers, 'atlas-timestamp');
        $signature = self::header($headers, 'atlas-signature');
        if ($id === null || $id === '' || $tsRaw === null || $tsRaw === '' || $signature === null || $signature === '') {
            return new PolicyFeedVerification(false, 'missing_headers');
        }

        $provided = [];
        foreach (preg_split('/\s+/', trim($signature), -1, PREG_SPLIT_NO_EMPTY) ?: [] as $entry) {
            if (str_starts_with($entry, 'v1,')) {
                $decoded = base64_decode(substr($entry, 3), false);
                $provided[] = $decoded === false ? '' : $decoded;
            }
        }
        if ($provided === []) {
            return new PolicyFeedVerification(false, 'malformed_signature');
        }

        if (!is_numeric($tsRaw) || !is_finite((float) $tsRaw)) {
            return new PolicyFeedVerification(false, 'stale_timestamp');
        }
        $timestamp = (float) $tsRaw;
        $now = $nowMs ?? (int) floor(microtime(true) * 1000);
        if (abs($now - $timestamp) > $toleranceMs) {
            return new PolicyFeedVerification(false, 'stale_timestamp');
        }

        $expected = self::mac($secret, $id, $tsRaw, "GET\n" . $pathAndQuery);
        // Compare every entry (no early exit), each in constant time.
        $matched = false;
        foreach ($provided as $candidate) {
            if (hash_equals($expected, $candidate)) {
                $matched = true;
            }
        }
        if (!$matched) {
            return new PolicyFeedVerification(false, 'signature_mismatch');
        }

        $key = null;
        $q = strpos($pathAndQuery, '?');
        if ($q !== false) {
            parse_str(substr($pathAndQuery, $q + 1), $query);
            if (isset($query['key']) && is_string($query['key'])) {
                $key = $query['key'];
            }
        }

        return new PolicyFeedVerification(true, null, $id, (int) $timestamp, $key, self::header($headers, self::VERSION_HEADER));
    }

    /**
     * Sign a `policy.published` / `policy.retired` event for `POST {webhook_url}` (`atlas-id`,
     * `atlas-timestamp` in ms, `atlas-signature: v1,<base64 HMAC-SHA256 of "{id}.{ts}.{body}">`).
     * `occurred_at` defaults to now (ISO 8601). Atlas also accepts Standard Webhooks signatures if
     * you prefer a library for that.
     *
     *     $signed = PolicySourceProtocol::signEvent(['type' => 'policy.published', 'key' => 'tos', 'version' => '2026-10-24'], $secret);
     *     // POST $signed->body to the webhook_url with 'Content-Type: application/json' and $signed->headers
     *
     * @param array<string,mixed> $event
     */
    public static function signEvent(array $event, string $secret, ?string $id = null, ?int $timestampMs = null): SignedPolicySourceEvent
    {
        $now = (int) floor(microtime(true) * 1000);
        $occurredAt = gmdate('Y-m-d\TH:i:s', intdiv($now, 1000)) . sprintf('.%03dZ', $now % 1000);
        $body = json_encode(array_merge(['occurred_at' => $occurredAt], $event), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $id ??= 'evt_' . bin2hex(random_bytes(16));
        $ts = (string) ($timestampMs ?? $now);

        return new SignedPolicySourceEvent($body, [
            'atlas-id' => $id,
            'atlas-timestamp' => $ts,
            'atlas-signature' => 'v1,' . base64_encode(self::mac($secret, $id, $ts, $body)),
        ]);
    }

    private static function mac(string $secret, string $id, string $timestamp, string $payload): string
    {
        return hash_hmac('sha256', $id . '.' . $timestamp . '.' . $payload, $secret, true);
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
