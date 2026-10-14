<?php

declare(strict_types=1);

namespace Atlas\Verify;

use Atlas\Exception\ConfigException;
use Firebase\JWT\BeforeValidException;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\JWK;
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
 *     $claims = $logout->verify($_POST['logout_token'] ?? '');   // throws InvalidLogoutTokenException
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
     * @throws InvalidLogoutTokenException for any token that must be refused (answer 400)
     */
    public function verify(string $logoutToken): LogoutTokenClaims
    {
        if (count(explode('.', $logoutToken)) !== 3) {
            throw new InvalidLogoutTokenException('malformed');
        }

        $keys = $this->jwks->get(JwksCache::readKid($logoutToken));
        if ($keys === null || $keys['keys'] === []) {
            throw new InvalidLogoutTokenException('no_keys');
        }

        try {
            $keySet = JWK::parseKeySet($keys, 'RS256');
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
            // parseKeySet pins every key to RS256, so any other `alg` fails here.
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
