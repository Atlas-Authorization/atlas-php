<?php

declare(strict_types=1);

namespace Atlas\Verify;

use Atlas\Exception\ConfigException;
use Firebase\JWT\JWT;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;

/**
 * Verifies Atlas-issued OAuth access tokens for your own API (an OAuth 2.0
 * resource server), locally against the instance JWKS — RS256 or ES256, nothing
 * else: the issuer, expiry, that it IS an access token, that its `aud` names your
 * API (RFC 8707), and the scopes a route needs. A refusal carries the RFC 6750 /
 * RFC 9728 `WWW-Authenticate` challenge, so an OAuth or MCP client can discover
 * Atlas from the 401 (`resource_metadata`) or ask for the missing scope.
 *
 * Sender-constrained tokens (RFC 9449 DPoP) are enforced: a token bound to a key
 * (`cnf.jkt`) is accepted only with the `DPoP` scheme and a valid DPoP proof from
 * that key, never as a plain Bearer token. `requireDpop` refuses unbound tokens
 * altogether, and `dpopNonces` demands a server nonce.
 */
final class AccessTokenVerifier
{
    private readonly string $issuer;
    /** @var list<string>|null */
    private readonly ?array $audiences;
    /** @var list<string> */
    private readonly array $requiredScopes;
    private readonly JwksCache $jwks;
    /** @var callable():int|null */
    private $now;
    private readonly JtiStore $dpopReplay;
    private readonly ?Introspector $introspector;

    /**
     * @param string|list<string>|false $audience       your API identifier(s); `false` only to skip the audience check
     * @param list<string>              $requiredScopes scopes every request must carry
     * @param callable():int|null       $now            clock override (UNIX seconds), for tests
     * @param bool                      $requireDpop    refuse every token that is not DPoP-bound and
     *                                                  challenge with the `DPoP` scheme
     * @param string|null               $publicOrigin   this API's public origin, for the DPoP `htu` when
     *                                                  the request URL is a path (set it behind a proxy)
     * @param JtiStore|null             $dpopReplayStore records DPoP proof `jti`s; default an
     *                                                  {@see InMemoryJtiStore} (one process: under
     *                                                  PHP-FPM pass a shared store)
     * @param DpopNonceSource|null      $dpopNonces     when set, every proof must carry a nonce it
     *                                                  issued; others are refused with `use_dpop_nonce`
     * @param Introspector|array{clientId:string,clientSecret:string,endpoint?:string,cacheTtl?:int|float,negativeCacheTtl?:int|float,cacheMax?:int,introspectJwts?:bool}|null $introspection
     *        validate opaque access tokens (and, with `introspectJwts`, JWTs too) at Atlas's
     *        RFC 7662 introspection endpoint with your API's client credentials, cached briefly
     */
    public function __construct(
        string $issuer,
        string|array|false $audience,
        array $requiredScopes = [],
        ?string $jwksUrl = null,
        private readonly ?string $resourceMetadataUrl = null,
        private readonly ?string $realm = null,
        ?ClientInterface $httpClient = null,
        ?RequestFactoryInterface $requestFactory = null,
        ?callable $now = null,
        private readonly bool $requireDpop = false,
        private readonly ?string $publicOrigin = null,
        ?JtiStore $dpopReplayStore = null,
        private readonly ?DpopNonceSource $dpopNonces = null,
        Introspector|array|null $introspection = null,
    ) {
        if ($issuer === '') {
            throw new ConfigException('AccessTokenVerifier requires an issuer.');
        }
        if ($audience !== false && ($audience === '' || $audience === [])) {
            throw new ConfigException('AccessTokenVerifier requires an audience (or audience: false).');
        }
        $this->issuer = rtrim($issuer, '/');
        $this->audiences = $audience === false ? null : (is_string($audience) ? [$audience] : array_values($audience));
        $this->requiredScopes = array_values($requiredScopes);
        $this->now = $now;
        $this->dpopReplay = $dpopReplayStore ?? new InMemoryJtiStore($now);
        $factory = ($httpClient === null || $requestFactory === null) ? new \GuzzleHttp\Psr7\HttpFactory() : null;
        if (is_array($introspection)) {
            $introspection = new Introspector(
                issuer: $this->issuer,
                clientId: (string) ($introspection['clientId'] ?? ''),
                clientSecret: (string) ($introspection['clientSecret'] ?? ''),
                endpoint: $introspection['endpoint'] ?? null,
                cacheTtl: $introspection['cacheTtl'] ?? 60,
                negativeCacheTtl: $introspection['negativeCacheTtl'] ?? 10,
                cacheMax: $introspection['cacheMax'] ?? 10_000,
                introspectJwts: (bool) ($introspection['introspectJwts'] ?? false),
                httpClient: $httpClient,
                requestFactory: $requestFactory,
                now: $now,
            );
        }
        $this->introspector = $introspection;
        $this->jwks = new JwksCache(
            url: $jwksUrl ?? $this->issuer . '/.well-known/jwks.json',
            client: $httpClient ?? new \GuzzleHttp\Client(['timeout' => 10]),
            requestFactory: $requestFactory ?? $factory,
            now: $now === null ? null : static fn (): int => (int) ($now() * 1000),
        );
    }

    /**
     * Verify a PSR-7 request: its `Authorization` and `DPoP` headers, method and URL.
     *
     * @param list<string> $requiredScopes added to the configured ones for this call
     */
    public function verifyRequest(\Psr\Http\Message\RequestInterface $request, array $requiredScopes = []): AccessTokenResult
    {
        $uri = $request->getUri();
        $path = $uri->getPath() === '' ? '/' : $uri->getPath();
        if ($uri->getQuery() !== '') {
            $path .= '?' . $uri->getQuery();
        }
        $dpop = $request->getHeader('DPoP');

        return $this->verify(
            $request->getHeaderLine('Authorization'),
            $requiredScopes,
            $request->getMethod(),
            $this->publicOrigin !== null || $uri->getHost() === '' ? $path : (string) $uri->withFragment(''),
            count($dpop) === 1 ? $dpop[0] : null, // several DPoP headers are no proof at all
        );
    }

    /**
     * Verify an `Authorization` header value (`Bearer` or `DPoP` scheme). `$method`,
     * `$url` (absolute, or a path resolved against `publicOrigin`) and `$dpop` (the
     * `DPoP` header) are what a DPoP proof is checked against. Never throws for a
     * bad token.
     *
     * @param list<string> $requiredScopes added to the configured ones for this call
     */
    public function verify(
        ?string $authorization,
        array $requiredScopes = [],
        string $method = 'GET',
        string $url = '',
        ?string $dpop = null,
    ): AccessTokenResult {
        $required = array_values(array_merge($this->requiredScopes, $requiredScopes));
        $nonce = $this->dpopNonces?->current();
        $deny = fn (int $status, ?string $error, string $description, ?string $scheme = null): AccessTokenResult
            => $this->refuse($status, $error, $description, $required, $scheme, $nonce);
        $value = trim((string) $authorization);
        if ($value === '') {
            return $deny(401, null, 'Authentication required.');
        }
        if (preg_match('/^(Bearer|DPoP)\s+([A-Za-z0-9\-._~+\/]+=*)$/i', $value, $m) !== 1) {
            return $deny(400, 'invalid_request', 'Malformed Authorization header.');
        }
        $presentedDpop = strcasecmp($m[1], 'dpop') === 0;
        $token = $m[2];
        $invalid = 'The access token is invalid or expired.';
        $isJwt = count(explode('.', $token)) === 3;
        if (!$isJwt || ($this->introspector !== null && $this->introspector->introspectJwts)) {
            // An opaque token (or a JWT the caller wants checked live) is validated by Atlas itself.
            if ($this->introspector === null) {
                return $deny(401, 'invalid_token', 'The access token is not a JWT; configure `introspection` to accept opaque tokens.');
            }
            try {
                $claims = $this->introspector->introspect($token);
            } catch (IntrospectionException) {
                return $deny(401, 'invalid_token', 'The access token could not be validated right now.');
            }
            if ($claims === null) {
                return $deny(401, 'invalid_token', $invalid);
            }
            if (array_key_exists('iss', $claims) && rtrim((string) $claims['iss'], '/') !== $this->issuer) {
                return $deny(401, 'invalid_token', 'The access token was issued by another authorization server.');
            }
            $nowSeconds = $this->now !== null ? (float) ($this->now)() : microtime(true);
            if (isset($claims['exp']) && is_numeric($claims['exp'])
                && (float) $claims['exp'] + SessionVerifier::CLOCK_SKEW_SECONDS <= $nowSeconds) {
                return $deny(401, 'invalid_token', $invalid);
            }
        } else {
            $keys = $this->jwks->get(JwksCache::readKid($token));
            if ($keys === null || $keys['keys'] === []) {
                return $deny(401, 'invalid_token', 'No signing keys are available to verify the token.');
            }

            $previousLeeway = JWT::$leeway;
            $previousTimestamp = JWT::$timestamp;
            JWT::$leeway = SessionVerifier::CLOCK_SKEW_SECONDS;
            if ($this->now !== null) {
                JWT::$timestamp = (int) ($this->now)();
            }
            try {
                $payload = JWT::decode($token, SigningKeys::parse($keys));
                /** @var array<string,mixed> $claims */
                $claims = json_decode(json_encode($payload, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);
            } catch (\Throwable) {
                return $deny(401, 'invalid_token', $invalid);
            } finally {
                JWT::$leeway = $previousLeeway;
                JWT::$timestamp = $previousTimestamp;
            }

            if (($claims['iss'] ?? null) !== $this->issuer || !isset($claims['exp'])) {
                return $deny(401, 'invalid_token', $invalid);
            }
        }
        if (array_key_exists('token_use', $claims) && $claims['token_use'] !== 'access_token') {
            return $deny(401, 'invalid_token', 'The token is not an access token.');
        }
        if ($this->audiences !== null) {
            $aud = $claims['aud'] ?? [];
            $aud = is_array($aud) ? $aud : [$aud];
            if (array_intersect($aud, $this->audiences) === []) {
                return $deny(401, 'invalid_token', 'The access token was not issued for this resource.');
            }
        }
        // Sender constraint (RFC 9449). A bound token is only ever a DPoP token.
        $jkt = null;
        if (is_array($claims['cnf'] ?? null) && array_key_exists('jkt', $claims['cnf'])) {
            $jkt = $claims['cnf']['jkt'];
            if (!is_string($jkt) || $jkt === '') {
                return $deny(401, 'invalid_token', $invalid);
            }
        }
        if ($jkt !== null || $this->requireDpop || $presentedDpop) {
            if ($jkt === null) {
                return $deny(401, 'invalid_token', 'This resource requires a DPoP-bound access token.', 'DPoP');
            }
            if (!$presentedDpop) {
                return $deny(401, 'invalid_token', 'A DPoP-bound access token must be sent with the DPoP scheme.', 'DPoP');
            }
            if ($dpop === null || trim($dpop) === '') {
                return $deny(401, 'invalid_dpop_proof', 'A DPoP proof is required.', 'DPoP');
            }
            $refusal = Dpop::checkProof(
                trim($dpop),
                $token,
                $jkt,
                strtoupper($method === '' ? 'GET' : $method),
                $url,
                $this->publicOrigin,
                $this->now !== null ? ($this->now)() : microtime(true),
                $this->dpopReplay,
                $this->dpopNonces,
            );
            if ($refusal !== null) {
                return $deny(401, $refusal[0], $refusal[1], 'DPoP');
            }
        }
        $scopes = is_string($claims['scope'] ?? null) ? array_values(array_filter(explode(' ', $claims['scope']))) : [];
        $granted = $scopes;
        foreach ((array) ($claims['permissions'] ?? []) as $p) {
            if (is_string($p)) {
                $granted[] = $p;
            }
        }
        $missing = array_values(array_diff($required, $granted));
        if ($missing !== []) {
            return $deny(
                403,
                'insufficient_scope',
                'The access token is missing the required scope(s): ' . implode(' ', $missing) . '.',
            );
        }
        $clientId = $claims['client_id'] ?? $claims['azp'] ?? null;

        return new AccessTokenResult(
            ok: true,
            claims: $claims,
            scopes: $scopes,
            subject: (string) ($claims['sub'] ?? ''),
            clientId: is_string($clientId) ? $clientId : null,
            dpopBound: $jkt !== null,
            dpopNonce: $nonce,
        );
    }

    /**
     * An RFC 6750 / RFC 9449 / RFC 9728 challenge (`$scheme` `Bearer` or `DPoP`;
     * a DPoP challenge also lists the accepted proof algorithms). Without `$error`
     * it is the bare "authentication required" challenge (no token was sent).
     *
     * @param list<string>|null $scope
     */
    public static function buildWwwAuthenticate(
        ?string $realm = null,
        ?string $error = null,
        ?string $description = null,
        ?array $scope = null,
        ?string $resourceMetadataUrl = null,
        string $scheme = 'Bearer',
    ): string {
        $q = static fn (string $v): string => '"' . addcslashes($v, '"\\') . '"';
        $parts = [];
        if ($realm !== null) {
            $parts[] = 'realm=' . $q($realm);
        }
        if ($error !== null) {
            $parts[] = 'error=' . $q($error);
            if ($description !== null) {
                $parts[] = 'error_description=' . $q($description);
            }
        }
        if ($scope !== null && $scope !== []) {
            $parts[] = 'scope=' . $q(implode(' ', $scope));
        }
        if ($resourceMetadataUrl !== null) {
            $parts[] = 'resource_metadata=' . $q($resourceMetadataUrl);
        }

        $scheme = $scheme === 'DPoP' ? 'DPoP' : 'Bearer';
        if ($scheme === 'DPoP') {
            $parts[] = 'algs=' . $q(implode(' ', Dpop::ALGS));
        }

        return $parts === [] ? $scheme : $scheme . ' ' . implode(', ', $parts);
    }

    /**
     * The RFC 9728 protected-resource metadata document for your API.
     * `$dpopBoundAccessTokensRequired` advertises that tokens must be DPoP-bound
     * (pair it with `requireDpop`).
     *
     * @param list<string>|null $scopesSupported
     *
     * @return array<string,mixed>
     */
    public static function protectedResourceMetadata(
        string $resource,
        string $issuer,
        ?array $scopesSupported = null,
        bool $dpopBoundAccessTokensRequired = false,
    ): array
    {
        $doc = [
            'resource' => $resource,
            'authorization_servers' => [rtrim($issuer, '/')],
            'bearer_methods_supported' => ['header'],
        ];
        if ($scopesSupported !== null && $scopesSupported !== []) {
            $doc['scopes_supported'] = $scopesSupported;
        }
        $doc['dpop_signing_alg_values_supported'] = Dpop::ALGS;
        if ($dpopBoundAccessTokensRequired) {
            $doc['dpop_bound_access_tokens_required'] = true;
        }

        return $doc;
    }

    /** The RFC 9728 well-known path for a resource identifier. */
    public static function protectedResourceMetadataPath(string $resource): string
    {
        $path = rtrim((string) parse_url($resource, PHP_URL_PATH), '/');

        return '/.well-known/oauth-protected-resource' . $path;
    }

    /** @param list<string> $required */
    private function refuse(
        int $status,
        ?string $error,
        string $description,
        array $required,
        ?string $scheme,
        ?string $nonce,
    ): AccessTokenResult {
        $scope = ($error === null || $error === 'insufficient_scope') ? $required : null;

        return new AccessTokenResult(
            ok: false,
            status: $status,
            error: $error,
            description: $description,
            wwwAuthenticate: self::buildWwwAuthenticate(
                $this->realm,
                $error,
                $description,
                $scope,
                $this->resourceMetadataUrl,
                $scheme ?? ($this->requireDpop ? 'DPoP' : 'Bearer'),
            ),
            dpopNonce: $nonce,
        );
    }
}
