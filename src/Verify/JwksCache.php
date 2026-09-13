<?php

declare(strict_types=1);

namespace Atlas\Verify;

use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;

/**
 * §7.3: "JWKS cached in-process with kid-miss refetch (max 1/min) so key
 * rotation needs no deploys."
 *
 * The rate limit on the refetch is the security property, not a politeness.
 * Without it, an attacker sends tokens carrying random `kid` values and every
 * one forces an outbound request to the customer's JWKS endpoint — turning any
 * unauthenticated caller into a traffic amplifier aimed at Atlas, from inside
 * the customer's own infrastructure. The cache's job is as much to refuse to
 * fetch as it is to fetch.
 *
 * The other half of the requirement is that rotation needs no deploy. A key that
 * rotated an hour ago must be picked up automatically, which is exactly what the
 * kid-miss path does — but only for a kid the cache has genuinely not seen, and
 * only once a minute.
 *
 * Time is tracked in milliseconds to mirror the reference (`verify.ts`); an
 * injectable `now` closure lets a test drive the clock with no real sleeping.
 */
final class JwksCache
{
    /** §7.3: at most one refetch per minute, however many misses arrive. */
    public const REFETCH_INTERVAL_MS = 60_000;

    /** §7.3 serves `Cache-Control: max-age=3600`; honoured rather than ignored. */
    public const DEFAULT_TTL_MS = 3_600_000;

    /** @var array{keys:array<int,array<string,mixed>>}|null */
    private ?array $cached = null;
    private int $fetchedAt = 0;
    private int $lastAttemptAt = 0;

    /**
     * The outcome of the last {@see get()} call — exposed so a caller (or a test)
     * can assert the rate limit actually bit. One of: `fresh`, `cached`,
     * `refetched`, `throttled`, `failed`.
     */
    public string $lastOutcome = 'fresh';

    /** @var callable(): int returns the current time in milliseconds */
    private $now;

    public function __construct(
        private readonly string $url,
        private readonly ClientInterface $client,
        private readonly RequestFactoryInterface $requestFactory,
        ?callable $now = null,
        private readonly int $ttlMs = self::DEFAULT_TTL_MS,
        private readonly int $refetchIntervalMs = self::REFETCH_INTERVAL_MS,
    ) {
        $this->now = $now ?? static fn (): int => (int) (microtime(true) * 1000);
    }

    /**
     * The JWKS to verify against, refetching if this `kid` is unknown.
     *
     * Returns whatever is cached when a refetch is throttled or fails. That is
     * deliberate: a stale JWKS still verifies every token signed by a key it
     * contains, so degrading to "the keys I already had" keeps the overwhelming
     * majority of requests working through a JWKS outage. Only tokens signed by a
     * brand-new key fail, and those are a minute old at most.
     *
     * @return array{keys:array<int,array<string,mixed>>}|null
     */
    public function get(?string $kid = null): ?array
    {
        $now = ($this->now)();

        $expired = $this->cached === null || $now - $this->fetchedAt >= $this->ttlMs;
        $kidMiss = $this->cached !== null && !$this->has($kid);

        if (!$expired && !$kidMiss) {
            $this->lastOutcome = 'cached';

            return $this->cached;
        }

        // The throttle. An attacker sending random kids must not be able to make
        // this process hammer the JWKS endpoint — so a miss inside the window is
        // answered from cache, and the token simply fails to verify.
        if ($kidMiss && !$expired && $now - $this->lastAttemptAt < $this->refetchIntervalMs) {
            $this->lastOutcome = 'throttled';

            return $this->cached;
        }

        $this->lastAttemptAt = $now;

        try {
            $body = $this->fetch();
            $this->cached = $body;
            $this->fetchedAt = $now;
            $this->lastOutcome = $kidMiss ? 'refetched' : 'fresh';

            return $this->cached;
        } catch (\Throwable) {
            // Keep serving what we have. A JWKS outage should degrade to "new keys
            // do not work yet", not "nobody can authenticate".
            $this->lastOutcome = 'failed';

            return $this->cached;
        }
    }

    private function has(?string $kid): bool
    {
        if ($this->cached === null) {
            return false;
        }
        if ($kid === null || $kid === '') {
            return $this->cached['keys'] !== [];
        }
        foreach ($this->cached['keys'] as $key) {
            if (($key['kid'] ?? null) === $kid) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{keys:array<int,array<string,mixed>>}
     *
     * @throws \RuntimeException on a non-2xx status or a malformed body
     */
    private function fetch(): array
    {
        $request = $this->requestFactory
            ->createRequest('GET', $this->url)
            ->withHeader('Accept', 'application/json');

        try {
            $response = $this->client->sendRequest($request);
        } catch (ClientExceptionInterface $e) {
            throw new \RuntimeException('JWKS fetch failed: ' . $e->getMessage(), 0, $e);
        }

        $status = $response->getStatusCode();
        if ($status < 200 || $status >= 300) {
            throw new \RuntimeException("JWKS fetch failed ({$status})");
        }

        $decoded = json_decode((string) $response->getBody(), true);
        if (!is_array($decoded) || !isset($decoded['keys']) || !is_array($decoded['keys'])) {
            throw new \RuntimeException('JWKS is malformed');
        }

        return ['keys' => array_values($decoded['keys'])];
    }

    /** Test and diagnostic surface — never used for a security decision. */
    public function snapshot(): array
    {
        return ['keys' => $this->cached === null ? 0 : count($this->cached['keys']), 'fetchedAt' => $this->fetchedAt];
    }

    /** Read the `kid` from a JWT header without verifying anything. */
    public static function readKid(string $jwt): ?string
    {
        $parts = explode('.', $jwt);
        if ($parts[0] === '' || !isset($parts[0])) {
            return null;
        }
        $json = self::base64UrlDecode($parts[0]);
        if ($json === null) {
            return null;
        }
        $header = json_decode($json, true);

        return is_array($header) && isset($header['kid']) && is_string($header['kid']) ? $header['kid'] : null;
    }

    private static function base64UrlDecode(string $data): ?string
    {
        $remainder = strlen($data) % 4;
        if ($remainder !== 0) {
            $data .= str_repeat('=', 4 - $remainder);
        }
        $decoded = base64_decode(strtr($data, '-_', '+/'), true);

        return $decoded === false ? null : $decoded;
    }
}
