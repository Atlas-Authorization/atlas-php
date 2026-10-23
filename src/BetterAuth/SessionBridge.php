<?php

declare(strict_types=1);

namespace Atlas\BetterAuth;

use Atlas\Client;

/**
 * The bridge as a function of a request: call it before your normal auth check. It does nothing (no
 * network) for a request without a Better Auth cookie or with an Atlas session (`__session` /
 * `__atlas_rt`) already present.
 *
 *     $bridge = new SessionBridge(new \Atlas\Client(getenv('ATLAS_SECRET_KEY')));
 *     $decision = $bridge->decide($_SERVER['HTTP_COOKIE'] ?? '', $clientIp, $_SERVER['HTTP_USER_AGENT'] ?? null);
 */
final class SessionBridge
{
    /** @var \Closure(Client,string,?string,?string):ExchangeResult */
    private readonly \Closure $exchange;

    /**
     * @param Client      $client                 an {@see Client} on the instance secret key
     * @param string      $cookiePrefix           Better Auth's `advanced.cookiePrefix`
     * @param string|null $cookieName             the session cookie's full name, when the old app overrode it
     * @param string|null $betterAuthCookieDomain the `domain` the Better Auth cookies were set on
     *                                            (`crossSubDomainCookies.domain`), so they clear
     * @param bool        $secure                 mark the Atlas cookies `Secure` (false only for plain-http local development)
     * @param callable(Client,string,?string,?string):ExchangeResult|null $exchange override the exchange (tests)
     */
    public function __construct(
        private readonly Client $client,
        public readonly string $cookiePrefix = BetterAuth::COOKIE_PREFIX,
        public readonly ?string $cookieName = null,
        public readonly ?string $betterAuthCookieDomain = null,
        public readonly bool $secure = true,
        ?callable $exchange = null,
    ) {
        $this->exchange = $exchange !== null
            ? \Closure::fromCallable($exchange)
            : static fn (Client $c, string $t, ?string $ip, ?string $ua): ExchangeResult => BetterAuth::exchange($c, $t, $ip, $ua);
    }

    /**
     * Decide for one request.
     *
     * @param string|array<string,mixed>|null $cookies   the `Cookie` header or a parsed cookie map
     * @param string|null                     $clientIp  the end user's IP as your stack resolved it
     * @param string|null                     $userAgent the end user's user agent
     */
    public function decide(string|array|null $cookies, ?string $clientIp = null, ?string $userAgent = null): BridgeDecision
    {
        $jar = is_array($cookies) ? $cookies : BetterAuth::parseCookieHeader($cookies);
        $token = BetterAuth::readSessionCookie($jar, $this->cookiePrefix, $this->cookieName);
        // Already on Atlas: leave the old cookie for the old app's own expiry.
        if ($token === null || ($jar['__session'] ?? '') !== '' || ($jar['__atlas_rt'] ?? '') !== '') {
            return new BridgeDecision(BridgeDecision::NONE);
        }

        return $this->decideFor(($this->exchange)($this->client, $token, $clientIp, $userAgent));
    }

    /**
     * The framework-neutral request policy shared by the Laravel and Symfony adapters (and matching
     * the PSR-15 middleware): GET / HEAD only; signed in redirects to `$url`; a second factor owed
     * redirects to `$signInUrl` (continues when null); a refusal continues, clearing the Better Auth
     * cookies only when it is final.
     *
     * @param string|array<string,mixed>|null $cookies
     */
    public function plan(string $method, string|array|null $cookies, string $url, ?string $clientIp, ?string $userAgent, ?string $signInUrl = null): BridgePlan
    {
        $method = strtoupper($method);
        if ($method !== 'GET' && $method !== 'HEAD') {
            return new BridgePlan();
        }
        $decision = $this->decide($cookies, $clientIp, $userAgent !== '' ? $userAgent : null);
        if ($decision->action === BridgeDecision::SIGNED_IN) {
            return new BridgePlan($url, $decision->setCookieHeaders());
        }
        if ($decision->action === BridgeDecision::NEEDS_MFA && $signInUrl !== null && $signInUrl !== '') {
            return new BridgePlan($decision->continuationUrl($signInUrl), $decision->setCookieHeaders());
        }

        return new BridgePlan(null, $decision->setCookieHeaders());
    }

    /** The decision for an exchange outcome. */
    public function decideFor(ExchangeResult $result): BridgeDecision
    {
        if ($result->kind === ExchangeResult::SESSION) {
            return new BridgeDecision(
                BridgeDecision::SIGNED_IN,
                cookies: array_merge([
                    new BridgeCookie('__session', $result->jwt, sameSite: 'Lax', secure: $this->secure),
                    new BridgeCookie('__atlas_rt', $result->refreshToken, httpOnly: true, sameSite: 'Lax', secure: $this->secure),
                ], $this->clearCookies()),
                session: $result,
            );
        }
        if ($result->kind === ExchangeResult::NEEDS_MFA) {
            return new BridgeDecision(
                BridgeDecision::NEEDS_MFA,
                cookies: $this->clearCookies(),
                attemptId: $result->attemptId,
                status: $result->status,
            );
        }
        $outcome = $result->outcome ?? 'unavailable';

        return new BridgeDecision(
            BridgeDecision::REFUSED,
            cookies: in_array($outcome, BetterAuth::FINAL_OUTCOMES, true) ? $this->clearCookies() : [],
            outcome: $outcome,
        );
    }

    /**
     * Expired cookies for every Better Auth cookie name.
     *
     * @return list<BridgeCookie>
     */
    public function clearCookies(): array
    {
        $names = BetterAuth::cookieNames($this->cookiePrefix)['all'];
        if ($this->cookieName !== null && $this->cookieName !== '') {
            $names[] = $this->cookieName;
            $names[] = '__Secure-' . $this->cookieName;
        }

        return array_map(
            fn (string $n): BridgeCookie => new BridgeCookie(
                $n,
                '',
                domain: $this->betterAuthCookieDomain,
                // A `__Secure-` cookie can only be overwritten by a Secure Set-Cookie.
                secure: str_starts_with($n, '__Secure-'),
                maxAge: 0,
            ),
            $names,
        );
    }
}
