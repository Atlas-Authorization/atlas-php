<?php

declare(strict_types=1);

namespace Atlas;

/**
 * Status-aware retries for the Backend API client (opt-in: pass it as `Client`'s `resilience` argument).
 *
 * - Retries `429`, `502`, `503` and `504` answers and transport failures, at most `$maxRetries` times, for requests that are safe to
 *   repeat: GET and HEAD, PUT and DELETE, and any write sent with an `Idempotency-Key`. Other writes are never retried.
 * - Honours `Retry-After` (seconds or an HTTP date). A wait longer than `$maxDelayMs` is not slept: the error is thrown instead.
 * - Without `Retry-After`, backs off exponentially from `$baseDelayMs` with jitter, up to `$maxDelayMs`.
 * - With `$consultStatus`, a `503` first reads the service status endpoint (cached for `$statusTtlSeconds`): while Atlas reports an
 *   outage the client fails fast instead of piling retries onto it.
 *
 *     $atlas = new Client('sk_live_...', resilience: new Resilience(maxRetries: 3));
 */
final class Resilience
{
    /** @var callable(int):void */
    public $sleep;

    /**
     * @param list<int>             $retryStatuses
     * @param (callable(int):void)|null $sleep milliseconds to wait (tests pass a no-op that records)
     */
    public function __construct(
        public readonly int $maxRetries = 2,
        public readonly int $baseDelayMs = 250,
        public readonly int $maxDelayMs = 30_000,
        public readonly array $retryStatuses = [429, 502, 503, 504],
        public readonly bool $consultStatus = true,
        public readonly int $statusTtlSeconds = 30,
        ?callable $sleep = null,
    ) {
        $this->sleep = $sleep ?? static function (int $ms): void {
            usleep($ms * 1000);
        };
    }

    /** Whether a request is safe to send again after a failure. */
    public static function replayable(string $method, ?string $idempotencyKey): bool
    {
        return in_array(strtoupper($method), ['GET', 'HEAD', 'PUT', 'DELETE'], true) || ($idempotencyKey !== null && $idempotencyKey !== '');
    }

    /** Milliseconds to wait before attempt `$attempt` (1-based), or null when the server's `Retry-After` exceeds the cap. */
    public function delayMs(int $attempt, ?int $retryAfterSeconds): ?int
    {
        if ($retryAfterSeconds !== null) {
            $ms = $retryAfterSeconds * 1000;

            return $ms > $this->maxDelayMs ? null : $ms;
        }
        $ceiling = min($this->maxDelayMs, $this->baseDelayMs * (2 ** ($attempt - 1)));

        return random_int(intdiv($ceiling, 2), $ceiling);
    }

    /**
     * Seconds from a `Retry-After` header value (delta-seconds or an HTTP date), or null.
     */
    public static function parseRetryAfter(string $value, ?int $now = null): ?int
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        if (ctype_digit($value)) {
            return (int) $value;
        }
        $at = strtotime($value);

        return $at === false ? null : max(0, $at - ($now ?? time()));
    }

    /**
     * Whether a service status document reports an outage: a top-level `status` / `indicator` of `major_outage`, `outage`, `down` or
     * `critical`. An unfamiliar shape is not an outage (normal backoff applies).
     *
     * @param array<string,mixed> $doc
     */
    public static function reportsOutage(array $doc): bool
    {
        foreach ([$doc['status'] ?? null, $doc['indicator'] ?? null, $doc['overall'] ?? null] as $v) {
            if (is_string($v) && in_array(strtolower($v), ['major_outage', 'outage', 'down', 'critical'], true)) {
                return true;
            }
        }

        return false;
    }
}
