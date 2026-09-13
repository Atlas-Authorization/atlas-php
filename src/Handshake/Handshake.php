<?php

declare(strict_types=1);

namespace Atlas\Handshake;

use Atlas\Exception\ConfigException;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * Cross-property SSO handshake — a satellite's server-side redeem step, the PHP
 * port of `@atlas/backend`'s `handshake.ts`.
 *
 * When a satellite (a property on a DIFFERENT registrable domain than the main
 * app) has no local session, its route protection bounces the browser once
 * through `GET {fapiOrigin}/v1/client/handshake`, which — if the user has an
 * Atlas session — redirects back with
 * `?__atlas_hs=ok&__atlas_hu=<userId>&__atlas_hn=<nonce>` (an opaque single-use
 * nonce + a non-secret user id, NEVER a token). This helper exchanges that
 * nonce for a fresh session, server-to-server, so the tokens are returned in the
 * response BODY. The caller then sets them as its OWN first-party cookies:
 *
 *   $handshake = new \Atlas\Handshake\Handshake();
 *
 *   // On the return URL (e.g. /atlas/handshake):
 *   $params = \Atlas\Handshake\Handshake::readHandshakeParams($_SERVER['REQUEST_URI']);
 *   if ($params !== null) {
 *       // The PKCE verifier stashed when we bounced out (see handshakeRedirectUrl()).
 *       $verifier = $_COOKIE['__atlas_hv'] ?? '';
 *       setcookie('__atlas_hv', '', ['expires' => 1, 'path' => '/']); // single use
 *       $session = $handshake->redeemHandshake(
 *           fapiOrigin: getenv('ATLAS_FAPI_ORIGIN'),
 *           publishableKey: getenv('ATLAS_PUBLISHABLE_KEY'),
 *           userId: $params['userId'],
 *           nonce: $params['nonce'],
 *           codeVerifier: $verifier,
 *       );
 *       if ($session !== null) {
 *           // script-readable, short-lived — mirrors the `__session` cookie
 *           setcookie('__session', $session->jwt, [
 *               'path' => '/', 'samesite' => 'Lax', 'secure' => true,
 *           ]);
 *           // HttpOnly refresh cookie
 *           setcookie('__atlas_rt', $session->refreshToken, [
 *               'path' => '/', 'httponly' => true, 'samesite' => 'Lax', 'secure' => true,
 *           ]);
 *       }
 *       // Either way, redirect to the same URL with the __atlas_* params stripped.
 *   }
 *
 * (Same-registrable-domain SUBDOMAINS don't need this — the handshake sets a
 * parent-domain cookie directly; this is only the cross-domain path.)
 *
 * There is no secret key here: the redeem call authenticates with the instance
 * publishable key (`pk_…`) via `X-Publishable-Key`, so — like
 * {@see \Atlas\Verify\SessionVerifier} — this uses its own PSR-18 client rather
 * than {@see \Atlas\Http} (which always sends the secret key as a Bearer token
 * and throws on a non-2xx response).
 */
final class Handshake
{
    private readonly ClientInterface $client;
    private readonly RequestFactoryInterface $requestFactory;
    private readonly StreamFactoryInterface $streamFactory;

    /**
     * @param ClientInterface|null         $httpClient     PSR-18 client for the redeem call; Guzzle is auto-discovered when null
     * @param RequestFactoryInterface|null $requestFactory PSR-17 request factory; Guzzle is auto-discovered when null
     * @param StreamFactoryInterface|null  $streamFactory  PSR-17 stream factory; Guzzle is auto-discovered when null
     */
    public function __construct(
        ?ClientInterface $httpClient = null,
        ?RequestFactoryInterface $requestFactory = null,
        ?StreamFactoryInterface $streamFactory = null,
    ) {
        $this->client = $httpClient ?? self::guzzleClient();
        if ($requestFactory === null || $streamFactory === null) {
            $factory = self::guzzleFactory();
            $requestFactory ??= $factory;
            $streamFactory ??= $factory;
        }
        $this->requestFactory = $requestFactory;
        $this->streamFactory = $streamFactory;
    }

    /**
     * Redeem a handshake nonce for a fresh session.
     *
     * POSTs `{"user_id": …, "nonce": …, "code_verifier": …}` to
     * `{fapiOrigin}/v1/client/handshake/redeem` with an `X-Publishable-Key`
     * header. Returns a {@see HandshakeSession} on HTTP 2xx, or `null` when the
     * nonce is missing/expired/already used, OR the PKCE verifier fails to match
     * the challenge sent at bounce-out (all normal signed-out signals — the
     * caller should then fall back to sign-in). NEVER throws on an auth or
     * transport failure: a bad nonce should not take the page down.
     *
     * The `code_verifier` is the PKCE (RFC 7636, S256) proof: the server bound
     * the nonce to the `code_challenge` we sent when we bounced the browser out,
     * and only releases a session to whoever can present the matching verifier.
     * The nonce alone is NOT a bearer credential — it rides `__atlas_hn` on a
     * return URL that lands in access logs, `Referer` headers, and browser
     * history, so a leaked URL must not be redeemable. The verifier never
     * touches a URL: it is stashed server-side (an HttpOnly `__atlas_hv` cookie)
     * across the bounce and read back here. See {@see createPkcePair()}.
     *
     * @param string $fapiOrigin     origin of the Atlas Frontend API, e.g. `https://id.atlasauth.net`
     * @param string $publishableKey the instance publishable key (`pk_…`)
     * @param string $userId         the `__atlas_hu` value from the return URL
     * @param string $nonce          the single-use `__atlas_hn` nonce from the return URL
     * @param string $codeVerifier   the PKCE verifier stashed at bounce-out (e.g. the `__atlas_hv` cookie)
     */
    public function redeemHandshake(
        string $fapiOrigin,
        string $publishableKey,
        string $userId,
        string $nonce,
        string $codeVerifier,
    ): ?HandshakeSession {
        $url = rtrim($fapiOrigin, '/') . '/v1/client/handshake/redeem';

        try {
            $json = json_encode(
                ['user_id' => $userId, 'nonce' => $nonce, 'code_verifier' => $codeVerifier],
                JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
            );
        } catch (\JsonException) {
            return null;
        }

        $request = $this->requestFactory->createRequest('POST', $url)
            ->withHeader('X-Publishable-Key', $publishableKey)
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Accept', 'application/json')
            ->withBody($this->streamFactory->createStream($json));

        try {
            $response = $this->client->sendRequest($request);
        } catch (ClientExceptionInterface) {
            return null;
        }

        $status = $response->getStatusCode();
        if ($status < 200 || $status >= 300) {
            return null;
        }

        $data = json_decode((string) $response->getBody(), true);
        if (!is_array($data)) {
            return null;
        }

        $jwt = $data['jwt'] ?? null;
        $refreshToken = $data['refresh_token'] ?? null;
        if (!is_string($jwt) || $jwt === '' || !is_string($refreshToken) || $refreshToken === '') {
            return null;
        }

        $sessionId = $data['session_id'] ?? '';
        $expiresIn = $data['expires_in'] ?? 0;

        return new HandshakeSession(
            jwt: $jwt,
            refreshToken: $refreshToken,
            sessionId: is_string($sessionId) ? $sessionId : '',
            expiresIn: is_numeric($expiresIn) ? (int) $expiresIn : 0,
        );
    }

    /**
     * Extract the handshake params from a return URL (or its bare query string).
     *
     * Returns `['userId' => …, 'nonce' => …]` when `__atlas_hs` is `ok` and both
     * `__atlas_hu` and `__atlas_hn` are present and non-empty; otherwise `null`.
     *
     * @return array{userId: string, nonce: string}|null
     */
    public static function readHandshakeParams(string $urlOrQuery): ?array
    {
        $queryPos = strpos($urlOrQuery, '?');
        $query = $queryPos === false ? $urlOrQuery : substr($urlOrQuery, $queryPos + 1);

        // Drop a trailing fragment if the caller passed a full URL.
        $fragmentPos = strpos($query, '#');
        if ($fragmentPos !== false) {
            $query = substr($query, 0, $fragmentPos);
        }

        $params = [];
        parse_str($query, $params);

        if (($params['__atlas_hs'] ?? null) !== 'ok') {
            return null;
        }

        $userId = $params['__atlas_hu'] ?? null;
        $nonce = $params['__atlas_hn'] ?? null;
        if (!is_string($userId) || $userId === '' || !is_string($nonce) || $nonce === '') {
            return null;
        }

        return ['userId' => $userId, 'nonce' => $nonce];
    }

    /**
     * Generate a PKCE (RFC 7636) pair for the handshake, using the S256 method.
     *
     * `verifier` is the high-entropy secret (base64url of 32 random bytes, no
     * padding). `challenge` is its SHA-256 digest, base64url-no-padding —
     * `base64url(sha256(verifier))` — and is the ONLY half safe to put on a URL.
     *
     * WHY this exists: the handshake nonce (`__atlas_hn`) travels on a return URL
     * that is inherently loggable — it lands in web-server access logs, upstream
     * proxy logs, `Referer` headers to third parties, and browser history. On its
     * own that nonce must therefore NOT be sufficient to mint a session, or a
     * leaked URL would be an account-takeover primitive. PKCE closes that: we
     * send only the `challenge` when bouncing out (see {@see handshakeRedirectUrl()}),
     * keep the `verifier` off the wire (an HttpOnly `__atlas_hv` cookie), and the
     * server releases a session at redeem only to whoever presents the verifier
     * whose SHA-256 equals the challenge it recorded against the nonce.
     *
     * base64url here is `strtr(base64_encode($bytes), '+/', '-_')` with `=`
     * padding trimmed — the exact encoding the Atlas server verifies against.
     *
     * @return array{verifier: string, challenge: string}
     */
    public static function createPkcePair(): array
    {
        $verifier = self::base64UrlEncode(random_bytes(32));
        $challenge = self::base64UrlEncode(hash('sha256', $verifier, true));

        return ['verifier' => $verifier, 'challenge' => $challenge];
    }

    /**
     * Build the URL that triggers a handshake for a satellite WITHOUT PSR-15
     * middleware (this SDK ships none). On a protected request with no valid
     * local session, redirect the browser ONCE to the returned URL — guard it
     * with {@see shouldTriggerHandshake()} so a signed-out return never loops.
     *
     * Returns BOTH the redirect URL (with the PKCE `code_challenge` appended) and
     * the `verifier` the caller MUST stash across the bounce (HttpOnly cookie
     * `__atlas_hv`, path `/`, ~300s) and hand back to {@see redeemHandshake()} on
     * return. The verifier is deliberately NOT in the URL — see {@see createPkcePair()}.
     *
     *   if (Handshake::shouldTriggerHandshake($_SERVER['REQUEST_URI'])) {
     *       ['url' => $to, 'verifier' => $verifier] =
     *           Handshake::handshakeRedirectUrl($fapiOrigin, $pk, $currentUrl);
     *       setcookie('__atlas_hv', $verifier, [
     *           'path' => '/', 'max-age' => 300,
     *           'httponly' => true, 'samesite' => 'Lax', 'secure' => true,
     *       ]);
     *       header('Location: ' . $to, true, 302);
     *       exit;
     *   }
     *
     * @param string $fapiOrigin     origin of the Atlas Frontend API
     * @param string $publishableKey the instance publishable key (`pk_…`)
     * @param string $currentUrl     the URL to return to after the handshake
     *
     * @return array{url: string, verifier: string}
     */
    public static function handshakeRedirectUrl(
        string $fapiOrigin,
        string $publishableKey,
        string $currentUrl,
    ): array {
        $pkce = self::createPkcePair();

        $query = http_build_query([
            'publishable_key' => $publishableKey,
            'redirect_url' => $currentUrl,
            'code_challenge' => $pkce['challenge'],
        ]);

        return [
            'url' => rtrim($fapiOrigin, '/') . '/v1/client/handshake?' . $query,
            'verifier' => $pkce['verifier'],
        ];
    }

    /**
     * base64url with padding stripped — `strtr(base64_encode(...), '+/', '-_')`
     * minus `=`. The exact encoding the Atlas server uses to hash and compare the
     * PKCE verifier/challenge, kept in one place so both halves match byte-for-byte.
     */
    private static function base64UrlEncode(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }

    /**
     * Loop guard for {@see handshakeRedirectUrl()}: only trigger a handshake
     * when the request has NOT already come back from one (no `__atlas_hs`
     * param present), so a `signed_out` return doesn't bounce forever.
     */
    public static function shouldTriggerHandshake(string $urlOrQuery): bool
    {
        $queryPos = strpos($urlOrQuery, '?');
        $query = $queryPos === false ? $urlOrQuery : substr($urlOrQuery, $queryPos + 1);

        $params = [];
        parse_str($query, $params);

        return !array_key_exists('__atlas_hs', $params);
    }

    private static function guzzleClient(): ClientInterface
    {
        if (!class_exists(\GuzzleHttp\Client::class)) {
            throw new ConfigException(
                'No PSR-18 client was provided to Handshake and guzzlehttp/guzzle is not installed. '
                . 'Install it or pass your own client.',
            );
        }

        return new \GuzzleHttp\Client(['timeout' => 10, 'connect_timeout' => 5, 'http_errors' => false]);
    }

    private static function guzzleFactory(): RequestFactoryInterface&StreamFactoryInterface
    {
        if (!class_exists(\GuzzleHttp\Psr7\HttpFactory::class)) {
            throw new ConfigException(
                'No PSR-17 factories were provided to Handshake and guzzlehttp/psr7 is not installed. '
                . 'Install it or pass your own request/stream factories.',
            );
        }

        return new \GuzzleHttp\Psr7\HttpFactory();
    }
}
