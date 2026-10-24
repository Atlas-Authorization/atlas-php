<?php

declare(strict_types=1);

namespace Atlas;

/**
 * W3C Trace Context (`traceparent`) helpers. Atlas accepts a `traceparent` on any call and carries
 * its trace id onto the webhooks, back-channel logouts, integration events and privacy cases that
 * the call causes, so one operation can be followed across systems.
 *
 *     $atlas = $atlas->withOptions(traceparent: Traceparent::generate());
 *     $atlas->users->ban('user_1');           // every request carries the header
 *
 *     $incoming = Traceparent::parse($_SERVER['HTTP_TRACEPARENT'] ?? '');   // continue a caller's trace
 *     $child = Traceparent::child($incoming['traceparent'] ?? null);
 */
final class Traceparent
{
    public const HEADER = 'traceparent';

    /** A new traceparent: `00-<32 hex trace id>-<16 hex span id>-01` (sampled). */
    public static function generate(): string
    {
        return '00-' . bin2hex(random_bytes(16)) . '-' . bin2hex(random_bytes(8)) . '-01';
    }

    /**
     * A traceparent in the same trace as `$parent` with a fresh span id (a new trace when `$parent` is null or invalid).
     */
    public static function child(?string $parent): string
    {
        $parsed = $parent === null ? null : self::parse($parent);
        if ($parsed === null) {
            return self::generate();
        }

        return '00-' . $parsed['trace_id'] . '-' . bin2hex(random_bytes(8)) . '-' . $parsed['flags'];
    }

    /**
     * Parse a traceparent; null when it is not a valid version-00 value (all-zero ids are invalid).
     *
     * @return array{traceparent:string,trace_id:string,span_id:string,flags:string}|null
     */
    public static function parse(string $value): ?array
    {
        $value = strtolower(trim($value));
        if (preg_match('/^00-([0-9a-f]{32})-([0-9a-f]{16})-([0-9a-f]{2})$/', $value, $m) !== 1) {
            return null;
        }
        if ($m[1] === str_repeat('0', 32) || $m[2] === str_repeat('0', 16)) {
            return null;
        }

        return ['traceparent' => $value, 'trace_id' => $m[1], 'span_id' => $m[2], 'flags' => $m[3]];
    }

    /** The trace id of a traceparent, or null. Match it against `trace_id` / `trace` on deliveries and privacy requests. */
    public static function traceId(string $value): ?string
    {
        return self::parse($value)['trace_id'] ?? null;
    }
}
