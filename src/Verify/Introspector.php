<?php

declare(strict_types=1);

namespace Atlas\Verify;

use Atlas\Exception\ConfigException;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;

/**
 * RFC 7662 token introspection at Atlas, for opaque (reference) access tokens.
 *
 * An API, client or instance set to the `opaque` access-token format gets `oat_…`
 * tokens only Atlas can read. The introspector asks Atlas with your API's own client
 * credentials (client_secret_basic) and caches the answer by the token's SHA-256 (the
 * token itself is never kept). The client must be a confidential client the API lists
 * in `introspection_client_ids` (or the client the tokens are issued to).
 *
 * The cache lives in this object: under PHP-FPM it lasts one request unless you keep
 * the verifier in a long-running worker.
 */
final class Introspector
{
    private readonly string $endpoint;
    private readonly string $basic;
    private readonly ClientInterface $client;
    private readonly RequestFactoryInterface $requestFactory;
    /** @var callable():float */
    private $now;
    /** @var array<string,array{0:array<string,mixed>|null,1:float}> */
    private array $cache = [];

    /**
     * @param int|float          $cacheTtl         seconds an active answer is reused (never past `exp`)
     * @param int|float          $negativeCacheTtl seconds an inactive answer is reused (0 disables)
     * @param bool               $introspectJwts   also send JWT access tokens to Atlas, so a revoked
     *                                             grant or a banned user is refused before expiry
     * @param callable():(int|float)|null $now     clock override (UNIX seconds), for tests
     */
    public function __construct(
        string $issuer,
        string $clientId,
        string $clientSecret,
        ?string $endpoint = null,
        private readonly int|float $cacheTtl = 60,
        private readonly int|float $negativeCacheTtl = 10,
        private readonly int $cacheMax = 10_000,
        public readonly bool $introspectJwts = false,
        ?ClientInterface $httpClient = null,
        ?RequestFactoryInterface $requestFactory = null,
        ?callable $now = null,
    ) {
        if ($clientId === '' || $clientSecret === '') {
            throw new ConfigException('Introspection needs a client id and secret.');
        }
        $this->endpoint = $endpoint ?? rtrim($issuer, '/') . '/oauth2/introspect';
        $this->basic = 'Basic ' . base64_encode(urlencode($clientId) . ':' . urlencode($clientSecret));
        $this->client = $httpClient ?? new \GuzzleHttp\Client(['timeout' => 10]);
        $this->requestFactory = $requestFactory ?? new \GuzzleHttp\Psr7\HttpFactory();
        $this->now = $now === null ? static fn (): float => microtime(true) : static fn (): float => (float) $now();
    }

    public static function cacheKey(string $token): string
    {
        return rtrim(strtr(base64_encode(hash('sha256', $token, true)), '+/', '-_'), '=');
    }

    /**
     * The claims of an active access token, or null for an inactive one (also for a
     * refresh token).
     *
     * @return array<string,mixed>|null
     *
     * @throws IntrospectionException when Atlas could not be asked
     */
    public function introspect(string $token): ?array
    {
        $key = self::cacheKey($token);
        $now = ($this->now)();
        if (isset($this->cache[$key])) {
            if ($this->cache[$key][1] > $now) {
                return $this->cache[$key][0];
            }
            unset($this->cache[$key]);
        }

        $request = $this->requestFactory->createRequest('POST', $this->endpoint)
            ->withHeader('Authorization', $this->basic)
            ->withHeader('Accept', 'application/json')
            ->withHeader('Content-Type', 'application/x-www-form-urlencoded')
            ->withBody(\GuzzleHttp\Psr7\Utils::streamFor(http_build_query(
                ['token' => $token, 'token_type_hint' => 'access_token'],
                '',
                '&',
                PHP_QUERY_RFC1738,
            )));
        try {
            $response = $this->client->sendRequest($request);
        } catch (\Throwable $e) {
            throw new IntrospectionException('Introspection request failed: ' . $e->getMessage(), 0, $e);
        }
        $status = $response->getStatusCode();
        if ($status < 200 || $status > 299) {
            throw new IntrospectionException("Introspection failed with HTTP {$status}.");
        }
        $body = json_decode((string) $response->getBody(), true);
        if (!is_array($body)) {
            throw new IntrospectionException('Introspection answered with invalid JSON.');
        }
        $claims = ($body['active'] ?? null) === true && ($body['token_use'] ?? null) !== 'refresh_token' ? $body : null;

        $now = ($this->now)();
        if ($claims !== null) {
            $until = $now + $this->cacheTtl;
            if (isset($claims['exp']) && is_numeric($claims['exp'])) {
                $until = min($until, (float) $claims['exp']);
            }
        } else {
            $until = $now + $this->negativeCacheTtl;
        }
        if ($until > $now) {
            unset($this->cache[$key]);
            $this->cache[$key] = [$claims, $until];
            if (count($this->cache) > $this->cacheMax) {
                foreach ($this->cache as $k => $entry) {
                    if ($entry[1] <= $now) {
                        unset($this->cache[$k]);
                    }
                }
                while (count($this->cache) > $this->cacheMax) {
                    unset($this->cache[array_key_first($this->cache)]);
                }
            }
        }

        return $claims;
    }

    /** How many answers are cached (for tests and metrics). */
    public function cacheSize(): int
    {
        return count($this->cache);
    }
}
