<?php

declare(strict_types=1);

namespace Atlas\Privacy;

use Atlas\Traceparent;
use Atlas\Webhook\Webhook;
use Atlas\Webhook\WebhookVerification;

/**
 * For a data-rights orchestrator that Atlas calls: verify what Atlas sends.
 *
 * - {@see verifyCaseRequest()}: the case Atlas opens at your `case_url` (and the cancel call at `cancel_url`) when the
 *   orchestrator has a signing secret (`sign_requests`). Signed like a webhook: `atlas-id`, `atlas-timestamp` (epoch ms) and
 *   `atlas-signature` (`v1,<base64 HMAC-SHA256>` over `<id>.<ts>.<raw body>`), five-minute window. The `Idempotency-Key`
 *   is the same on every retry: open one case per key.
 * - {@see verifyCompletion()}: the `privacy_request.updated` / `privacy_request.completed` events Atlas posts to the
 *   managed completion webhook, signed with that endpoint's secret (add its URL for `v2` signatures).
 *
 *     $result = PrivacyExecutor::verifyCaseRequest(file_get_contents('php://input'), getallheaders(), $secret);
 *     if (!$result->verified) { http_response_code(400); exit; }
 *     $case = $result->event;            // {type, source, caseRef, executeAfter, subject: {emails, atlas}}
 *     $trace = PrivacyExecutor::traceparent(getallheaders());
 */
final class PrivacyExecutor
{
    /** The `User-Agent` Atlas sends privacy calls with. */
    public const USER_AGENT = 'Atlas-Privacy/1';

    /**
     * @param array<string, string|list<string>> $headers
     */
    public static function verifyCaseRequest(
        string $body,
        array $headers,
        string $secret,
        ?int $nowMs = null,
        int $toleranceMs = Webhook::REPLAY_TOLERANCE_MS,
    ): WebhookVerification {
        return Webhook::verify($body, $headers, $secret, $nowMs, $toleranceMs);
    }

    /**
     * @param array<string, string|list<string>> $headers
     * @param string|null                        $url     the completion webhook URL, to also accept `v2` (URL-covering) signatures
     */
    public static function verifyCompletion(
        string $body,
        array $headers,
        string $secret,
        ?string $url = null,
        ?int $nowMs = null,
    ): WebhookVerification {
        $result = Webhook::verify($body, $headers, $secret, $nowMs, Webhook::REPLAY_TOLERANCE_MS, $url);
        if (!$result->verified) {
            return $result;
        }
        $type = is_array($result->event) ? ($result->event['type'] ?? null) : null;
        if (!in_array($type, ['privacy_request.updated', 'privacy_request.completed'], true)) {
            return new WebhookVerification(false, 'unexpected_event');
        }

        return $result;
    }

    /**
     * The `traceparent` Atlas sent on a case call (null when absent or invalid): pass it on when you call back.
     *
     * @param array<string, string|list<string>> $headers
     */
    public static function traceparent(array $headers): ?string
    {
        foreach ($headers as $name => $value) {
            if (strcasecmp((string) $name, 'traceparent') === 0 || strcasecmp((string) $name, 'HTTP_TRACEPARENT') === 0) {
                $value = is_array($value) ? ($value[0] ?? '') : $value;

                return Traceparent::parse((string) $value)['traceparent'] ?? null;
            }
        }

        return null;
    }
}
