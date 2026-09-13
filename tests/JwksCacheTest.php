<?php

declare(strict_types=1);

namespace Atlas\Tests;

use Atlas\Tests\Support\MockClient;
use Atlas\Verify\JwksCache;
use GuzzleHttp\Psr7\HttpFactory;
use PHPUnit\Framework\TestCase;

/**
 * The cache's security property is that it refuses to fetch: a flood of random
 * `kid` values must not become a flood of outbound JWKS requests.
 */
final class JwksCacheTest extends TestCase
{
    /** @var int milliseconds; a mutable clock the test advances by hand */
    private int $clock = 1_000_000;

    private function cache(MockClient $mock): JwksCache
    {
        return new JwksCache(
            url: 'https://issuer.test/.well-known/jwks.json',
            client: $mock,
            requestFactory: new HttpFactory(),
            now: fn (): int => $this->clock,
        );
    }

    public function testFirstGetFetchesThenServesFromCache(): void
    {
        $mock = new MockClient();
        $mock->push(200, ['keys' => [['kid' => 'a']]]);
        $cache = $this->cache($mock);

        $first = $cache->get('a');
        $this->assertNotNull($first);
        $this->assertSame('fresh', $cache->lastOutcome);
        $this->assertSame(1, $mock->requestCount());

        // Second read for a known kid is served from cache — no new request.
        $cache->get('a');
        $this->assertSame('cached', $cache->lastOutcome);
        $this->assertSame(1, $mock->requestCount());
    }

    public function testUnknownKidWithinTheWindowIsThrottledNotFetched(): void
    {
        $mock = new MockClient();
        $mock->push(200, ['keys' => [['kid' => 'a']]]);
        $cache = $this->cache($mock);

        $cache->get('a');                 // primes the cache (1 request)
        $this->clock += 5_000;            // 5s later, still inside the 60s window

        // A miss for an unknown kid must NOT fetch — it is answered from cache.
        $result = $cache->get('attacker-supplied-kid');
        $this->assertSame('throttled', $cache->lastOutcome);
        $this->assertSame(1, $mock->requestCount(), 'the throttle must suppress the refetch');
        $this->assertNotNull($result);
    }

    public function testUnknownKidAfterTheWindowRefetches(): void
    {
        $mock = new MockClient();
        $mock->push(200, ['keys' => [['kid' => 'a']]]);
        $mock->push(200, ['keys' => [['kid' => 'a'], ['kid' => 'b']]]);
        $cache = $this->cache($mock);

        $cache->get('a');                 // 1 request
        $this->clock += JwksCache::REFETCH_INTERVAL_MS + 1;

        $result = $cache->get('b');       // window elapsed → one refetch allowed
        $this->assertSame('refetched', $cache->lastOutcome);
        $this->assertSame(2, $mock->requestCount());
        $this->assertNotNull($result);
        $this->assertCount(2, $result['keys']);
    }

    public function testFetchFailureDegradesToStaleCache(): void
    {
        $mock = new MockClient();
        $mock->push(200, ['keys' => [['kid' => 'a']]]);
        $cache = $this->cache($mock);
        $cache->get('a');

        // Force expiry, then fail the refetch: we keep serving what we had.
        $this->clock += JwksCache::DEFAULT_TTL_MS + 1;
        $mock->push(500, 'upstream down');

        $result = $cache->get('a');
        $this->assertSame('failed', $cache->lastOutcome);
        $this->assertNotNull($result);
        $this->assertSame('a', $result['keys'][0]['kid']);
    }

    public function testReadKidParsesHeaderWithoutVerifying(): void
    {
        $header = rtrim(strtr(base64_encode(json_encode(['alg' => 'RS256', 'kid' => 'xyz'])), '+/', '-_'), '=');
        $token = $header . '.payload.sig';
        $this->assertSame('xyz', JwksCache::readKid($token));
        $this->assertNull(JwksCache::readKid('not-a-jwt'));
    }
}
