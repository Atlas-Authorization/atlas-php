<?php

declare(strict_types=1);

namespace Atlas\Verify;

use Atlas\Exception\ConfigException;
use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * §7.3 session-token verification for customer backends — the PHP port of
 * `verify.ts`.
 *
 * "verifies signature, exp/nbf with 5 s clock-skew tolerance, iss, and
 * optionally azp against an allowlist."
 *
 * The design constraint that matters is what this does NOT do: it never calls
 * Atlas on the hot path. A customer's API handling ten thousand requests a
 * second cannot make ten thousand outbound calls to verify them, and a verifier
 * that did would make Atlas's availability the customer's availability. So the
 * default path is local RS256 verification against cached JWKS, and the
 * revocation window is bounded instead by the 60-second token lifetime.
 *
 * {@see verifyOnline()} exists for the cases where 60 seconds is too long, and is
 * documented as the slow path so nobody reaches for it by default.
 */
final class SessionVerifier
{
    /** §7.3: five seconds either side, matching the server's minting tolerance. */
    public const CLOCK_SKEW_SECONDS = 5;

    private readonly JwksCache $jwks;
    private readonly ClientInterface $client;
    private readonly RequestFactoryInterface $requestFactory;
    private readonly ?StreamFactoryInterface $streamFactory;

    /** @var list<string> */
    private readonly array $authorizedParties;

    /** @var callable(): int|null returns the current time in UNIX seconds */
    private $now;

    /**
     * @param string                       $jwksUrl            the instance's JWKS URL
     * @param string                       $issuer            expected `iss`; required — an unchecked issuer accepts any Atlas instance
     * @param list<string>                 $authorizedParties §7.3 optional azp allowlist; when set, a token minted for a different origin is refused
     * @param string|null                  $secretKey         needed only for {@see verifyOnline()}
     * @param string|null                  $bapiBaseUrl       needed only for {@see verifyOnline()}
     * @param ClientInterface|null          $httpClient        PSR-18 client for JWKS + online verification; Guzzle is auto-discovered when null
     * @param RequestFactoryInterface|null  $requestFactory    PSR-17 request factory; Guzzle is auto-discovered when null
     * @param StreamFactoryInterface|null   $streamFactory     PSR-17 stream factory; Guzzle is auto-discovered when null (online verify only)
     * @param callable():int|null           $now               clock override (UNIX seconds), for tests
     * @param int                          $leeway            clock-skew tolerance in seconds
     */
    public function __construct(
        private readonly string $jwksUrl,
        private readonly string $issuer,
        array $authorizedParties = [],
        private readonly ?string $secretKey = null,
        private readonly ?string $bapiBaseUrl = null,
        ?ClientInterface $httpClient = null,
        ?RequestFactoryInterface $requestFactory = null,
        ?StreamFactoryInterface $streamFactory = null,
        ?callable $now = null,
        private readonly int $leeway = self::CLOCK_SKEW_SECONDS,
    ) {
        if ($issuer === '') {
            throw new ConfigException('SessionVerifier requires a non-empty issuer; an unchecked issuer accepts any Atlas instance.');
        }

        $this->client = $httpClient ?? self::guzzleClient();
        if ($requestFactory === null || $streamFactory === null) {
            $factory = self::guzzleFactory();
            $requestFactory ??= $factory;
            $streamFactory ??= $factory;
        }
        $this->requestFactory = $requestFactory;
        $this->streamFactory = $streamFactory;
        $this->authorizedParties = array_values($authorizedParties);
        $this->now = $now;

        $this->jwks = new JwksCache(
            url: $jwksUrl,
            client: $this->client,
            requestFactory: $this->requestFactory,
            now: $now === null ? null : static fn (): int => (int) ($now() * 1000),
        );
    }

    /** Exposed so a caller (or a test) can inspect the cache's last outcome. */
    public function jwksCache(): JwksCache
    {
        return $this->jwks;
    }

    /**
     * Verify locally. No network call unless the `kid` is unknown, and at most
     * one of those a minute.
     */
    public function verify(string $token): VerifyResult
    {
        if ($token === '' || count(explode('.', $token)) !== 3) {
            return VerifyResult::fail('malformed');
        }

        $keys = $this->jwks->get(JwksCache::readKid($token));
        if ($keys === null || $keys['keys'] === []) {
            return VerifyResult::fail('no_keys');
        }

        try {
            $keySet = JWK::parseKeySet($keys, 'RS256');
        } catch (\Throwable) {
            return VerifyResult::fail('no_keys');
        }

        $previousLeeway = JWT::$leeway;
        $previousTimestamp = JWT::$timestamp;
        JWT::$leeway = $this->leeway;
        if ($this->now !== null) {
            JWT::$timestamp = (int) ($this->now)();
        }

        try {
            // `parseKeySet` pins each key to RS256, so a token whose header says
            // `alg: none` — or any algorithm coerced onto the key material —
            // fails to decode. Signature + exp/nbf (with leeway) are enforced here.
            $payload = JWT::decode($token, $keySet);
            /** @var array<string,mixed> $claims */
            $claims = json_decode(json_encode($payload, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            // One reason for every failure. Telling a caller whether the
            // signature, the issuer or the expiry was wrong helps someone
            // refining a forged token far more than it helps a developer.
            return VerifyResult::fail('invalid');
        } finally {
            JWT::$leeway = $previousLeeway;
            JWT::$timestamp = $previousTimestamp;
        }

        // §7.3 issuer check. `firebase/php-jwt` does not validate `iss`, so this
        // is the guard that stops a token from a different Atlas instance passing.
        if (($claims['iss'] ?? null) !== $this->issuer) {
            return VerifyResult::fail('invalid');
        }

        // §13.1 token-confusion guard. An OP access token (`token_use:'access_token'`)
        // and an id_token (carries `aud`) are signed with the SAME per-instance
        // RS256 key, issuer and `typ:'JWT'` header as a first-party session JWT.
        // Reject any token carrying an OP marker so a "Sign in with Atlas" RP
        // cannot replay one as a customer session. An ABSENT `token_use`/`aud` is
        // a valid session (backward-compat), so this never mass-invalidates a
        // live fleet.
        $tokenUse = $claims['token_use'] ?? null;
        if (($tokenUse !== null && $tokenUse !== 'session') || array_key_exists('aud', $claims)) {
            return VerifyResult::fail('invalid');
        }

        if ($this->authorizedParties !== []) {
            $azp = $claims['azp'] ?? null;
            if (!is_string($azp) || !in_array($azp, $this->authorizedParties, true)) {
                return VerifyResult::fail('unauthorized_party');
            }
        }

        return VerifyResult::ok($claims);
    }

    /**
     * §7.3 the documented slow path: ask Atlas whether the session is still live.
     *
     * Costs a round trip on every call, so it is for the handful of operations
     * where a 60-second revocation window is genuinely unacceptable — deleting an
     * account, moving money. Fails CLOSED: the caller reached for this precisely
     * because a stale answer was unacceptable, so an outage returns `invalid`
     * rather than silently falling back to the local result.
     *
     * @throws ConfigException when the online transport is not configured
     */
    public function verifyOnline(string $token): VerifyResult
    {
        $local = $this->verify($token);
        if (!$local->ok) {
            return $local;
        }

        if ($this->secretKey === null || $this->secretKey === '' || $this->bapiBaseUrl === null || $this->bapiBaseUrl === '') {
            throw new ConfigException(
                'verifyOnline needs secretKey and bapiBaseUrl. Without them it would silently fall back to local '
                . 'verification, which is the opposite of what the caller asked for.',
            );
        }

        if ($this->streamFactory === null) {
            throw new ConfigException('verifyOnline needs a PSR-17 stream factory to build the request body.');
        }

        $url = rtrim($this->bapiBaseUrl, '/') . '/v1/tokens/verify';
        $request = $this->requestFactory->createRequest('POST', $url)
            ->withHeader('Authorization', 'Bearer ' . $this->secretKey)
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Accept', 'application/json')
            ->withBody($this->streamFactory->createStream(json_encode(['token' => $token], JSON_UNESCAPED_SLASHES)));

        try {
            $response = $this->client->sendRequest($request);
            $status = $response->getStatusCode();
            if ($status < 200 || $status >= 300) {
                return VerifyResult::fail('invalid');
            }
            $body = json_decode((string) $response->getBody(), true);
            $verified = is_array($body) && ($body['verified'] ?? false) === true;

            return $verified ? $local : VerifyResult::fail('invalid');
        } catch (ClientExceptionInterface) {
            return VerifyResult::fail('invalid');
        }
    }

    /**
     * Verify whatever a request carries.
     *
     * §7.5: `__session` is readable by script for Authorization-header mode, so
     * both carriers are legitimate. The header wins, because a caller that set it
     * deliberately should not be overridden by a stale cookie.
     *
     * Accepts a PSR-7 message (anything with `getHeaderLine()`) or a plain,
     * case-insensitive `array<string,string>` of headers (e.g. from `$_SERVER`
     * after normalization, or a framework request).
     *
     * @param array<string,string>|object $request
     */
    public function authenticateRequest(array|object $request): VerifyResult
    {
        $authorization = $this->header($request, 'authorization');
        $cookieHeader = $this->header($request, 'cookie');

        $bearer = $authorization !== null && str_starts_with($authorization, 'Bearer ')
            ? substr($authorization, 7)
            : null;
        $fromCookie = $cookieHeader !== null ? self::readCookie($cookieHeader, '__session') : null;

        $token = $bearer ?? $fromCookie;
        if ($token === null || $token === '') {
            return VerifyResult::fail('malformed');
        }

        return $this->verify($token);
    }

    /**
     * @param array<string,string>|object $request
     */
    private function header(array|object $request, string $name): ?string
    {
        if (is_object($request) && method_exists($request, 'getHeaderLine')) {
            $value = $request->getHeaderLine($name);

            return $value === '' ? null : $value;
        }

        if (is_array($request)) {
            foreach ($request as $key => $value) {
                if (strcasecmp((string) $key, $name) === 0) {
                    return is_string($value) ? $value : null;
                }
            }
        }

        return null;
    }

    private static function readCookie(string $header, string $name): ?string
    {
        foreach (explode(';', $header) as $part) {
            $trimmed = trim($part);
            $eq = strpos($trimmed, '=');
            if ($eq === false) {
                continue;
            }
            if (substr($trimmed, 0, $eq) === $name) {
                return substr($trimmed, $eq + 1);
            }
        }

        return null;
    }

    private static function guzzleClient(): ClientInterface
    {
        if (!class_exists(\GuzzleHttp\Client::class)) {
            throw new ConfigException(
                'No PSR-18 client was provided to SessionVerifier and guzzlehttp/guzzle is not installed. '
                . 'Install it or pass your own client.',
            );
        }

        return new \GuzzleHttp\Client(['timeout' => 10, 'connect_timeout' => 5, 'http_errors' => false]);
    }

    private static function guzzleFactory(): RequestFactoryInterface&StreamFactoryInterface
    {
        if (!class_exists(\GuzzleHttp\Psr7\HttpFactory::class)) {
            throw new ConfigException(
                'No PSR-17 factories were provided to SessionVerifier and guzzlehttp/psr7 is not installed. '
                . 'Install it or pass your own request/stream factories.',
            );
        }

        return new \GuzzleHttp\Psr7\HttpFactory();
    }
}
