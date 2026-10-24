<?php

declare(strict_types=1);

namespace Atlas\Verify;

use Atlas\Exception\ConfigException;
use Firebase\JWT\BeforeValidException;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\JWT;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;

/**
 * Verify an OpenID Connect Back-Channel Logout token (OIDC Back-Channel Logout
 * 1.0, section 2.6) — the `logout_token` Atlas POSTs to an app's
 * `backchannel_logout_uri` when a user signs out.
 *
 * Checks: RS256 signature against the issuer's JWKS, `iss`, `aud` contains your
 * client id, `iat` present and not in the future, `exp` (when present) not
 * passed, `jti` present, `events` carries the back-channel logout event with an
 * object value, `sid` and/or `sub` present, no `nonce`, and a `typ` header
 * (when present) of `logout+jwt`.
 *
 *     $logout = new BackchannelLogout(
 *         issuer: 'https://atlasauth.net',
 *         jwksUrl: 'https://atlasauth.net/.well-known/jwks.json',
 *         clientId: getenv('ATLAS_CLIENT_ID'),
 *         jtiStore: new InMemoryJtiStore(),   // optional replay protection
 *     );
 *     try {
 *         $claims = $logout->verify($_POST['logout_token'] ?? '');
 *     } catch (InvalidLogoutTokenException $e) {
 *         // 503 + Retry-After makes Atlas retry (keys unreachable); 400 is final.
 *         if ($e->retryable) { header('Retry-After: 30'); }
 *         http_response_code($e->retryable ? 503 : 400);
 *         exit;
 *     }
 */
final class BackchannelLogout
{
    public const EVENT = 'http://schemas.openid.net/event/backchannel-logout';
    /** Allowed clock skew, seconds. */
    public const DEFAULT_LEEWAY_SECONDS = 60;
    /** How old a token without `exp` may be, seconds. */
    public const DEFAULT_MAX_AGE_SECONDS = 600;

    private readonly JwksCache $jwks;

    /** @var (callable():int)|null */
    private $now;

    /**
     * @param string                       $issuer   the expected `iss` (Sign in with Atlas: `https://atlasauth.net`;
     *                                               session-exchange apps: your instance's frontend API origin)
     * @param string                       $jwksUrl  where the issuer publishes its signing keys
     * @param string                       $clientId your app's client id (the token's `aud`)
     * @param JtiStore|null                $jtiStore optional replay guard
     * @param (callable():int)|null        $now      clock returning epoch seconds (tests)
     */
    public function __construct(
        private readonly string $issuer,
        string $jwksUrl,
        private readonly string $clientId,
        private readonly ?JtiStore $jtiStore = null,
        ?ClientInterface $httpClient = null,
        ?RequestFactoryInterface $requestFactory = null,
        ?callable $now = null,
        private readonly int $leeway = self::DEFAULT_LEEWAY_SECONDS,
        private readonly int $maxAge = self::DEFAULT_MAX_AGE_SECONDS,
    ) {
        if ($issuer === '' || $clientId === '') {
            throw new ConfigException('BackchannelLogout needs a non-empty issuer and clientId.');
        }
        if ($httpClient === null || $requestFactory === null) {
            if (!class_exists(\GuzzleHttp\Client::class)) {
                throw new ConfigException('Pass a PSR-18 client and PSR-17 request factory, or install guzzlehttp/guzzle.');
            }
            $httpClient ??= new \GuzzleHttp\Client(['timeout' => 10, 'connect_timeout' => 5, 'http_errors' => false]);
            $requestFactory ??= new \GuzzleHttp\Psr7\HttpFactory();
        }
        $this->now = $now;
        $this->jwks = new JwksCache(
            url: $jwksUrl,
            client: $httpClient,
            requestFactory: $requestFactory,
            now: $now === null ? null : static fn (): int => (int) ($now() * 1000),
        );
    }

    /**
     * Verify and dispatch in one call, and get the HTTP answer for Atlas: 200 after `$onLogout` ran, 400 for a token that
     * must be refused (final: Atlas does not retry), 503 + `Retry-After` when the issuer's keys could not be fetched
     * (Atlas retries). `$onLogout` receives the {@see LogoutTokenClaims} (end the sessions for `sub` / `sid`); an exception
     * it throws becomes a 503 so Atlas retries.
     *
     *     [$status, $headers] = $logout->handle($_POST['logout_token'] ?? '', fn ($claims) => $sessions->endFor($claims->sid, $claims->sub));
     *     http_response_code($status);
     *     foreach ($headers as $name => $value) { header("$name: $value"); }
     *
     * @param callable(LogoutTokenClaims):void $onLogout
     *
     * @return array{0:int,1:array<string,string>} the status and response headers
     */
    public function handle(string $logoutToken, callable $onLogout): array
    {
        try {
            $claims = $this->verify($logoutToken);
        } catch (InvalidLogoutTokenException $e) {
            return $e->retryable ? [503, ['Retry-After' => '30', 'Cache-Control' => 'no-store']] : [400, ['Cache-Control' => 'no-store']];
        }
        try {
            $onLogout($claims);
        } catch (\Throwable) {
            return [503, ['Retry-After' => '30', 'Cache-Control' => 'no-store']];
        }

        return [200, ['Cache-Control' => 'no-store']];
    }

    /**
     * @throws InvalidLogoutTokenException for any token that must be refused (answer 400, or 503 when retryable)
     */
    public function verify(string $logoutToken): LogoutTokenClaims
    {
        if (count(explode('.', $logoutToken)) !== 3) {
            throw new InvalidLogoutTokenException('malformed');
        }

        $kid = JwksCache::readKid($logoutToken);
        $keys = $this->jwks->get($kid);
        if ($keys === null || $keys['keys'] === []) {
            throw new InvalidLogoutTokenException('no_keys');
        }
        // A key Atlas just rotated to, whose refetch failed: temporary.
        if ($kid !== null && $this->jwks->lastOutcome === 'failed') {
            $known = array_filter($keys['keys'], static fn ($k) => is_array($k) && ($k['kid'] ?? null) === $kid);
            if ($known === []) {
                throw new InvalidLogoutTokenException('no_keys');
            }
        }

        try {
            $keySet = SigningKeys::parse($keys);
        } catch (\Throwable) {
            throw new InvalidLogoutTokenException('no_keys');
        }

        $now = $this->currentTime();
        $previousLeeway = JWT::$leeway;
        $previousTimestamp = JWT::$timestamp;
        JWT::$leeway = $this->leeway;
        JWT::$timestamp = $now;
        $header = new \stdClass();
        try {
            // SigningKeys pins every key to RS256 or ES256, so any other `alg` fails here.
            $payload = JWT::decode($logoutToken, $keySet, $header);
        } catch (ExpiredException) {
            throw new InvalidLogoutTokenException('expired');
        } catch (BeforeValidException) {
            throw new InvalidLogoutTokenException('issued_in_future');
        } catch (\Throwable) {
            throw new InvalidLogoutTokenException('invalid_signature');
        } finally {
            JWT::$leeway = $previousLeeway;
            JWT::$timestamp = $previousTimestamp;
        }

        $typ = $header->typ ?? null;
        if ($typ !== null && !in_array(strtolower((string) $typ), ['logout+jwt', 'application/logout+jwt'], true)) {
            throw new InvalidLogoutTokenException('wrong_type');
        }

        if (($payload->iss ?? null) !== $this->issuer) {
            throw new InvalidLogoutTokenException('wrong_issuer');
        }
        $aud = $payload->aud ?? null;
        $audiences = is_array($aud) ? $aud : [$aud];
        if (!in_array($this->clientId, $audiences, true)) {
            throw new InvalidLogoutTokenException('wrong_audience');
        }

        $iat = $payload->iat ?? null;
        if (!is_int($iat) && !is_float($iat)) {
            throw new InvalidLogoutTokenException('missing_claim', 'Logout token has no iat.');
        }
        if ($iat > $now + $this->leeway) {
            throw new InvalidLogoutTokenException('issued_in_future');
        }
        $exp = $payload->exp ?? null;
        if ($exp === null && $iat < $now - $this->maxAge - $this->leeway) {
            throw new InvalidLogoutTokenException('expired');
        }

        $jti = $payload->jti ?? null;
        if (!is_string($jti) || $jti === '') {
            throw new InvalidLogoutTokenException('missing_claim', 'Logout token has no jti.');
        }

        $events = $payload->events ?? null;
        if (!$events instanceof \stdClass || !property_exists($events, self::EVENT)) {
            throw new InvalidLogoutTokenException('missing_claim', 'Logout token has no back-channel logout event.');
        }
        if (!$events->{self::EVENT} instanceof \stdClass) {
            throw new InvalidLogoutTokenException('invalid_claim', 'Logout token event value must be an object.');
        }

        $sub = $payload->sub ?? null;
        $sid = $payload->sid ?? null;
        $hasSub = is_string($sub) && $sub !== '';
        $hasSid = is_string($sid) && $sid !== '';
        if (!$hasSub && !$hasSid) {
            throw new InvalidLogoutTokenException('missing_claim', 'Logout token has neither sub nor sid.');
        }
        if (property_exists($payload, 'nonce')) {
            throw new InvalidLogoutTokenException('nonce_present');
        }

        if ($this->jtiStore !== null) {
            $ttl = $exp !== null ? max((int) $exp - $now, 0) + $this->leeway : $this->maxAge + $this->leeway;
            if (!$this->jtiStore->add($jti, $ttl)) {
                throw new InvalidLogoutTokenException('replayed');
            }
        }

        /** @var array<string,mixed> $claims */
        $claims = json_decode(json_encode($payload, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);

        return new LogoutTokenClaims($hasSub ? $sub : null, $hasSid ? $sid : null, $jti, (int) $iat, $claims);
    }

    private function currentTime(): int
    {
        return $this->now !== null ? (int) ($this->now)() : time();
    }
}
