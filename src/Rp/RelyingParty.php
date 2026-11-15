<?php

declare(strict_types=1);

namespace Atlas\Rp;

use Atlas\Exception\ConfigException;
use Atlas\Verify\BackchannelLogout;
use Atlas\Verify\JwksCache;
use Atlas\Verify\LogoutTokenClaims;
use Atlas\Verify\SigningKeys;
use Firebase\JWT\JWT;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * Hosted-redirect OpenID Connect relying party: Atlas sign-in for any PHP server. Your server sends the user to Atlas's hosted
 * sign-in page, verifies who came back and keeps its own session.
 *
 * State, nonce and the PKCE (S256) verifier travel in one sealed, short-lived, httpOnly cookie ({@see SealedCookie}). The ID token's
 * algorithm is pinned (RS256 / ES256 from the issuer's JWKS) and `iss`, `aud` / `azp`, `exp` / `iat`, `nonce` and `at_hash` are checked.
 *
 *     $rp = new RelyingParty(issuer: 'https://atlasauth.net', clientId: $id, clientSecret: $secret,
 *         redirectUri: 'https://app.example.com/auth/callback', secret: $sessionSecret);
 *     $start = $rp->start(['return_to' => '/billing']);          // 302 to $start['url'], then send $start['set_cookie']
 *     $done  = $rp->callback($_GET, $_COOKIE[RelyingParty::STATE_COOKIE] ?? null);  // ['claims' => ..., 'tokens' => ..., 'return_to' => ...]
 *
 * The PSR-15 handler ({@see \Atlas\Psr15\AtlasRelyingPartyMiddleware}) and the Laravel and Symfony routes wrap these calls.
 */
final class RelyingParty
{
    public const STATE_COOKIE = 'atlas_rp_state';
    public const STATE_TTL_SECONDS = 600;
    /** Query parameters passed on to the authorize endpoint (plus your `forwardParams`). */
    private const PASSTHROUGH = ['prompt', 'login_hint', 'ui_locales', 'max_age', 'organization'];

    private readonly string $issuer;
    private readonly ClientInterface $client;
    private readonly RequestFactoryInterface $requests;
    private readonly StreamFactoryInterface $streams;
    private readonly SealedCookie $cookies;
    /** @var (callable():int)|null */
    private $now;
    /** @var array<string,mixed>|null */
    private ?array $discovery = null;
    private ?JwksCache $jwks = null;

    /**
     * @param string                     $issuer       `https://` + your Frontend API host (Sign in with Atlas: `https://atlasauth.net`)
     * @param string|null                $clientSecret needed for the token exchange of a confidential client
     * @param string                     $secret       32+ characters; seals the sign-in cookie
     * @param string|null                $secretKey    optional instance secret key: server-side revoke and keep-alive through the Backend API
     * @param list<string>               $forwardParams extra query parameters of the sign-in URL passed to `/oauth2/authorize`
     * @param string                     $clientAuth   `client_secret_basic` (default), `client_secret_post` or `none`
     * @param list<string>               $scopes
     * @param (callable():int)|null      $now          clock returning epoch seconds (tests)
     */
    public function __construct(
        string $issuer,
        private readonly string $clientId,
        private readonly string $redirectUri,
        string $secret,
        private readonly ?string $clientSecret = null,
        private readonly ?string $secretKey = null,
        private readonly ?string $postLogoutRedirectUri = null,
        private readonly array $forwardParams = [],
        private readonly string $clientAuth = 'client_secret_basic',
        private readonly array $scopes = ['openid', 'profile', 'email'],
        ?ClientInterface $httpClient = null,
        ?RequestFactoryInterface $requestFactory = null,
        ?StreamFactoryInterface $streamFactory = null,
        ?callable $now = null,
        private readonly ?SidStore $sidStore = null,
        private readonly string $apiUrl = \Atlas\Http::DEFAULT_API_URL,
    ) {
        if ($issuer === '' || $clientId === '' || $redirectUri === '') {
            throw new ConfigException('RelyingParty needs an issuer, a clientId and a redirectUri.');
        }
        $this->issuer = rtrim($issuer, '/');
        $this->cookies = new SealedCookie($secret);
        $this->now = $now;
        if ($httpClient === null || $requestFactory === null || $streamFactory === null) {
            if (!class_exists(\GuzzleHttp\Client::class)) {
                throw new ConfigException('Pass a PSR-18 client and PSR-17 factories, or install guzzlehttp/guzzle.');
            }
            $factory = new \GuzzleHttp\Psr7\HttpFactory();
            $httpClient ??= new \GuzzleHttp\Client(['timeout' => 10, 'connect_timeout' => 5, 'http_errors' => false]);
            $requestFactory ??= $factory;
            $streamFactory ??= $factory;
        }
        $this->client = $httpClient;
        $this->requests = $requestFactory;
        $this->streams = $streamFactory;
    }

    /** The sealed-cookie helper (also use it for your own session cookie). */
    public function cookies(): SealedCookie
    {
        return $this->cookies;
    }

    /**
     * Begin sign-in.
     *
     * @param array<string,mixed> $query the sign-in request's query: `return_to` (a path on your site) plus `prompt`, `login_hint`,
     *                                   `ui_locales`, `max_age`, `organization` and your `forwardParams`; anything else is dropped
     *
     * @return array{url:string,cookie:string,set_cookie:string,state:string}
     */
    public function start(array $query = []): array
    {
        $endpoints = $this->discover();
        $state = self::random(24);
        $nonce = self::random(24);
        $verifier = self::random(48);
        $returnTo = is_string($query['return_to'] ?? null) && preg_match('#^/(?!/|\\\\)#', $query['return_to']) === 1 ? $query['return_to'] : '/';
        $params = [
            'response_type' => 'code',
            'client_id' => $this->clientId,
            'redirect_uri' => $this->redirectUri,
            'scope' => implode(' ', $this->scopes),
            'state' => $state,
            'nonce' => $nonce,
            'code_challenge' => rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '='),
            'code_challenge_method' => 'S256',
        ];
        foreach ([...self::PASSTHROUGH, ...$this->forwardParams] as $name) {
            if (isset($query[$name]) && is_scalar($query[$name]) && $query[$name] !== '') {
                $params[$name] = (string) $query[$name];
            }
        }
        $cookie = $this->cookies->seal(['state' => $state, 'nonce' => $nonce, 'verifier' => $verifier, 'return_to' => $returnTo], self::STATE_TTL_SECONDS, $this->time());

        return [
            'url' => $endpoints['authorization_endpoint'] . '?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986),
            'cookie' => $cookie,
            'set_cookie' => SealedCookie::header(self::STATE_COOKIE, $cookie, self::STATE_TTL_SECONDS),
            'state' => $state,
        ];
    }

    /**
     * Finish sign-in: check the state, exchange the code, verify the ID token.
     *
     * @param array<string,mixed> $query       the callback's query (`code`, `state`, or `error`)
     * @param string|null         $stateCookie the value of the {@see STATE_COOKIE} cookie
     *
     * @return array{claims:array<string,mixed>,tokens:array<string,mixed>,return_to:string,clear_cookie:string}
     *
     * @throws RpException
     */
    public function callback(array $query, ?string $stateCookie): array
    {
        $clear = SealedCookie::clear(self::STATE_COOKIE);
        $saved = $this->cookies->open($stateCookie, $this->time());
        if ($saved === null) {
            throw new RpException($stateCookie === null || $stateCookie === '' ? 'state_missing' : 'state_expired', 'The sign-in cookie is missing or expired. Start again.');
        }
        if (is_string($query['error'] ?? null)) {
            throw new RpException($query['error'], is_string($query['error_description'] ?? null) ? $query['error_description'] : 'Sign-in was not completed.');
        }
        $state = $query['state'] ?? null;
        if (!is_string($state) || !hash_equals((string) $saved['state'], $state)) {
            throw new RpException('state_mismatch', 'The sign-in state does not match.');
        }
        $code = $query['code'] ?? null;
        if (!is_string($code) || $code === '') {
            throw new RpException('token_exchange_failed', 'The callback carried no code.');
        }
        $tokens = $this->tokenRequest([
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $this->redirectUri,
            'code_verifier' => (string) $saved['verifier'],
        ]);
        if (!is_string($tokens['id_token'] ?? null)) {
            throw new RpException('token_exchange_failed', 'The token response carried no id_token.');
        }
        $claims = $this->verifyIdToken($tokens['id_token'], (string) $saved['nonce'], is_string($tokens['access_token'] ?? null) ? $tokens['access_token'] : null);

        return ['claims' => $claims, 'tokens' => $tokens, 'return_to' => (string) $saved['return_to'], 'clear_cookie' => $clear];
    }

    /**
     * Verify an ID token: pinned algorithm, signature, `iss`, `aud` / `azp`, `exp` / `iat`, `nonce`, `at_hash`.
     *
     * @return array<string,mixed> the claims
     *
     * @throws RpException
     */
    public function verifyIdToken(string $idToken, string $nonce, ?string $accessToken = null): array
    {
        $endpoints = $this->discover();
        $jwks = $this->jwks ??= new JwksCache(
            url: $endpoints['jwks_uri'],
            client: $this->client,
            requestFactory: $this->requests,
            now: $this->now === null ? null : fn (): int => ($this->now)() * 1000,
        );
        $keys = $jwks->get(JwksCache::readKid($idToken));
        $previous = [JWT::$leeway, JWT::$timestamp];
        JWT::$leeway = 60;
        JWT::$timestamp = $this->time();
        try {
            if ($keys === null) {
                throw new \UnexpectedValueException('no keys');
            }
            $claims = json_decode(json_encode(SigningKeys::decode($idToken, $keys), JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            throw new RpException('id_token_invalid', 'The ID token did not verify.');
        } finally {
            [JWT::$leeway, JWT::$timestamp] = $previous;
        }
        $aud = (array) ($claims['aud'] ?? []);
        $azp = $claims['azp'] ?? null;
        $ok = ($claims['iss'] ?? null) === $this->issuer
            && in_array($this->clientId, $aud, true)
            && (count($aud) === 1 || $azp === $this->clientId)
            && ($azp === null || $azp === $this->clientId)
            && is_int($claims['iat'] ?? null) && $claims['iat'] <= $this->time() + 60
            && is_int($claims['exp'] ?? null);
        if (!$ok) {
            throw new RpException('id_token_invalid', 'The ID token is not for this app or issuer.');
        }
        if (!is_string($claims['nonce'] ?? null) || !hash_equals($nonce, $claims['nonce'])) {
            throw new RpException('nonce_mismatch', 'The ID token nonce does not match.');
        }
        if ($accessToken !== null && is_string($claims['at_hash'] ?? null)) {
            $half = substr(hash('sha256', $accessToken, true), 0, 16);
            if (!hash_equals(rtrim(strtr(base64_encode($half), '+/', '-_'), '='), $claims['at_hash'])) {
                throw new RpException('id_token_invalid', 'The ID token at_hash does not match the access token.');
            }
        }

        return $claims;
    }

    /**
     * Keep a session alive when it was last touched more than `$intervalSeconds` ago. With a `secretKey` it slides the Atlas session
     * (`POST /v1/sessions/{sid}/touch`); without one it keeps your app's own sign-in alive with the client credentials
     * (`POST /oauth2/session/touch`).
     *
     * @return 'skipped'|'touched'|'ended'
     */
    public function touchIfDue(string $sid, ?int $lastTouchedAt, int $intervalSeconds = 300): string
    {
        if ($lastTouchedAt !== null && $this->time() - $lastTouchedAt < $intervalSeconds) {
            return 'skipped';
        }
        if ($this->secretKey !== null && $this->secretKey !== '') {
            [$status] = $this->json('POST', $this->apiUrl . '/v1/sessions/' . rawurlencode($sid) . '/touch', null, ['Authorization' => 'Bearer ' . $this->secretKey]);

            return $status === 409 || $status === 404 ? 'ended' : ($status < 300 ? 'touched' : throw new RpException('touch_failed', "Touch answered $status.", $status));
        }
        [$status, $out] = $this->clientCredentialsCall('/oauth2/session/touch', $sid);

        return $status < 300 && ($out['status'] ?? 'touched') !== 'ended' ? 'touched' : 'ended';
    }

    /**
     * End your app's own sign-in under `$sid` from the server (its refresh tokens are revoked and a back-channel logout reaches every
     * one of your servers). The user's Atlas session and other apps stay signed in.
     *
     * @return 'ended'|'not_found'
     */
    public function endAppSession(string $sid): string
    {
        [$status, $out] = $this->clientCredentialsCall('/oauth2/session/end', $sid);

        return $status < 300 && ($out['status'] ?? 'ended') !== 'not_found' ? 'ended' : 'not_found';
    }

    /** The URL that signs the user out of Atlas (RP-initiated logout), or your `postLogoutRedirectUri` when the issuer has none. */
    public function signOutUrl(?string $idToken = null, ?string $state = null): string
    {
        $end = $this->discover()['end_session_endpoint'] ?? null;
        if (!is_string($end)) {
            return $this->postLogoutRedirectUri ?? '/';
        }
        $params = array_filter([
            'client_id' => $this->clientId,
            'id_token_hint' => $idToken,
            'post_logout_redirect_uri' => $this->postLogoutRedirectUri,
            'state' => $state,
        ], static fn ($v): bool => $v !== null);

        return $end . '?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    }

    /** Record which of your sessions the Atlas `sid` backs (needs a {@see SidStore}); call it when you create your session. */
    public function bindSession(string $sid, string $appSessionId, int $expiresAt): void
    {
        ($this->sidStore ?? throw new ConfigException('bindSession needs a SidStore.'))->bind($sid, $appSessionId, $expiresAt);
    }

    /**
     * Handle a back-channel logout POST: verify the `logout_token`, take the sessions bound to its `sid` and hand them to
     * `$onLogout(list<string> $appSessionIds, LogoutTokenClaims $claims)`. Answer with the returned status and headers.
     *
     * @param callable(list<string>,LogoutTokenClaims):void $onLogout
     *
     * @return array{0:int,1:array<string,string>}
     */
    public function backchannelLogout(string $logoutToken, callable $onLogout): array
    {
        $verifier = new BackchannelLogout($this->issuer, $this->discover()['jwks_uri'], $this->clientId, null, $this->client, $this->requests, $this->now);

        return $verifier->handle($logoutToken, function (LogoutTokenClaims $claims) use ($onLogout): void {
            $ids = $claims->sid !== null && $this->sidStore !== null ? $this->sidStore->take($claims->sid) : [];
            $onLogout($ids, $claims);
        });
    }

    /** @return array<string,mixed> */
    private function discover(): array
    {
        if ($this->discovery !== null) {
            return $this->discovery;
        }
        [$status, $doc] = $this->json('GET', $this->issuer . '/.well-known/openid-configuration');
        if ($status !== 200 || !is_string($doc['authorization_endpoint'] ?? null) || !is_string($doc['token_endpoint'] ?? null) || !is_string($doc['jwks_uri'] ?? null)) {
            throw new RpException('discovery_failed', "Discovery at {$this->issuer} answered $status.", 502);
        }
        if (rtrim((string) ($doc['issuer'] ?? ''), '/') !== $this->issuer) {
            throw new RpException('discovery_failed', 'The discovery document names another issuer.', 502);
        }

        return $this->discovery = $doc;
    }

    /**
     * @param array<string,string> $fields
     *
     * @return array<string,mixed>
     */
    private function tokenRequest(array $fields): array
    {
        $headers = [];
        if ($this->clientAuth === 'client_secret_basic' && $this->clientSecret !== null) {
            $headers['Authorization'] = 'Basic ' . base64_encode(rawurlencode($this->clientId) . ':' . rawurlencode($this->clientSecret));
        } else {
            $fields['client_id'] = $this->clientId;
            if ($this->clientAuth === 'client_secret_post' && $this->clientSecret !== null) {
                $fields['client_secret'] = $this->clientSecret;
            }
        }
        [$status, $out] = $this->json('POST', $this->discover()['token_endpoint'], http_build_query($fields, '', '&', PHP_QUERY_RFC3986), $headers + ['Content-Type' => 'application/x-www-form-urlencoded']);
        if ($status !== 200) {
            throw new RpException('token_exchange_failed', (string) ($out['error_description'] ?? $out['error'] ?? "The token endpoint answered $status."), $status);
        }

        return $out;
    }

    /** @return array{0:int,1:array<string,mixed>} */
    private function clientCredentialsCall(string $path, string $sid): array
    {
        if ($this->clientSecret === null || $this->clientAuth === 'none') {
            throw new RpException('not_confidential', 'This call needs a confidential client (a clientSecret).');
        }
        $fields = ['sid' => $sid];
        $headers = ['Content-Type' => 'application/x-www-form-urlencoded'];
        if ($this->clientAuth === 'client_secret_post') {
            $fields += ['client_id' => $this->clientId, 'client_secret' => $this->clientSecret];
        } else {
            $headers['Authorization'] = 'Basic ' . base64_encode(rawurlencode($this->clientId) . ':' . rawurlencode($this->clientSecret));
        }

        return $this->json('POST', $this->issuer . $path, http_build_query($fields, '', '&', PHP_QUERY_RFC3986), $headers);
    }

    /**
     * @param array<string,string> $headers
     *
     * @return array{0:int,1:array<string,mixed>}
     */
    private function json(string $method, string $url, ?string $body = null, array $headers = []): array
    {
        $request = $this->requests->createRequest($method, $url)->withHeader('Accept', 'application/json');
        foreach ($headers as $name => $value) {
            $request = $request->withHeader($name, $value);
        }
        if ($body !== null) {
            $request = $request->withBody($this->streams->createStream($body));
        }
        try {
            $response = $this->client->sendRequest($request);
        } catch (\Psr\Http\Client\ClientExceptionInterface $e) {
            throw new RpException('discovery_failed', 'Could not reach ' . parse_url($url, PHP_URL_HOST) . ': ' . $e->getMessage(), 502);
        }
        $decoded = json_decode((string) $response->getBody(), true);

        return [$response->getStatusCode(), is_array($decoded) ? $decoded : []];
    }

    private function time(): int
    {
        return $this->now === null ? time() : ($this->now)();
    }

    private static function random(int $bytes): string
    {
        return rtrim(strtr(base64_encode(random_bytes($bytes)), '+/', '-_'), '=');
    }
}
