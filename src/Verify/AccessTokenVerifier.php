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
 * DPoP-bound tokens (`cnf.jkt`) are refused: this verifier checks Bearer tokens only.
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

    /**
     * @param string|list<string>|false $audience       your API identifier(s); `false` only to skip the audience check
     * @param list<string>              $requiredScopes scopes every request must carry
     * @param callable():int|null       $now            clock override (UNIX seconds), for tests
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
        $factory = ($httpClient === null || $requestFactory === null) ? new \GuzzleHttp\Psr7\HttpFactory() : null;
        $this->jwks = new JwksCache(
            url: $jwksUrl ?? $this->issuer . '/.well-known/jwks.json',
            client: $httpClient ?? new \GuzzleHttp\Client(['timeout' => 10]),
            requestFactory: $requestFactory ?? $factory,
            now: $now === null ? null : static fn (): int => (int) ($now() * 1000),
        );
    }

    /**
     * Verify an `Authorization` header value. Never throws for a bad token.
     *
     * @param list<string> $requiredScopes added to the configured ones for this call
     */
    public function verify(?string $authorization, array $requiredScopes = []): AccessTokenResult
    {
        $required = array_values(array_merge($this->requiredScopes, $requiredScopes));
        $value = trim((string) $authorization);
        if ($value === '') {
            return $this->refuse(401, null, 'Authentication required.', $required);
        }
        if (preg_match('/^(Bearer|DPoP)\s+([A-Za-z0-9\-._~+\/]+=*)$/i', $value, $m) !== 1) {
            return $this->refuse(400, 'invalid_request', 'Malformed Authorization header.', $required);
        }
        if (strcasecmp($m[1], 'dpop') === 0) {
            return $this->refuse(401, 'invalid_token', 'DPoP-bound access tokens are not accepted here.', $required);
        }
        $token = $m[2];
        if (count(explode('.', $token)) !== 3) {
            return $this->refuse(401, 'invalid_token', 'The access token is malformed.', $required);
        }
        $keys = $this->jwks->get(JwksCache::readKid($token));
        if ($keys === null || $keys['keys'] === []) {
            return $this->refuse(401, 'invalid_token', 'No signing keys are available to verify the token.', $required);
        }
        $invalid = 'The access token is invalid or expired.';

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
            return $this->refuse(401, 'invalid_token', $invalid, $required);
        } finally {
            JWT::$leeway = $previousLeeway;
            JWT::$timestamp = $previousTimestamp;
        }

        if (($claims['iss'] ?? null) !== $this->issuer || !isset($claims['exp'])) {
            return $this->refuse(401, 'invalid_token', $invalid, $required);
        }
        if (array_key_exists('token_use', $claims) && $claims['token_use'] !== 'access_token') {
            return $this->refuse(401, 'invalid_token', 'The token is not an access token.', $required);
        }
        if ($this->audiences !== null) {
            $aud = $claims['aud'] ?? [];
            $aud = is_array($aud) ? $aud : [$aud];
            if (array_intersect($aud, $this->audiences) === []) {
                return $this->refuse(401, 'invalid_token', 'The access token was not issued for this resource.', $required);
            }
        }
        if (is_array($claims['cnf'] ?? null) && array_key_exists('jkt', $claims['cnf'])) {
            return $this->refuse(401, 'invalid_token', 'DPoP-bound access tokens are not accepted here.', $required);
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
            return $this->refuse(
                403,
                'insufficient_scope',
                'The access token is missing the required scope(s): ' . implode(' ', $missing) . '.',
                $required,
            );
        }
        $clientId = $claims['client_id'] ?? $claims['azp'] ?? null;

        return new AccessTokenResult(
            ok: true,
            claims: $claims,
            scopes: $scopes,
            subject: (string) ($claims['sub'] ?? ''),
            clientId: is_string($clientId) ? $clientId : null,
        );
    }

    /**
     * An RFC 6750 / RFC 9728 Bearer challenge. Without `$error` it is the bare
     * "authentication required" challenge (no token was sent).
     *
     * @param list<string>|null $scope
     */
    public static function buildWwwAuthenticate(
        ?string $realm = null,
        ?string $error = null,
        ?string $description = null,
        ?array $scope = null,
        ?string $resourceMetadataUrl = null,
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

        return $parts === [] ? 'Bearer' : 'Bearer ' . implode(', ', $parts);
    }

    /**
     * The RFC 9728 protected-resource metadata document for your API.
     *
     * @param list<string>|null $scopesSupported
     *
     * @return array<string,mixed>
     */
    public static function protectedResourceMetadata(string $resource, string $issuer, ?array $scopesSupported = null): array
    {
        $doc = [
            'resource' => $resource,
            'authorization_servers' => [rtrim($issuer, '/')],
            'bearer_methods_supported' => ['header'],
        ];
        if ($scopesSupported !== null && $scopesSupported !== []) {
            $doc['scopes_supported'] = $scopesSupported;
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
    private function refuse(int $status, ?string $error, string $description, array $required): AccessTokenResult
    {
        $scope = ($error === null || $error === 'insufficient_scope') ? $required : null;

        return new AccessTokenResult(
            ok: false,
            status: $status,
            error: $error,
            description: $description,
            wwwAuthenticate: self::buildWwwAuthenticate($this->realm, $error, $description, $scope, $this->resourceMetadataUrl),
        );
    }
}
