<?php

declare(strict_types=1);

namespace Atlas\BetterAuth;

/**
 * What the bridge decided for one request.
 *
 * - `none`: no Better Auth cookie, or an Atlas session is already present. Nothing to do.
 * - `signed_in`: set {@see $cookies} and re-enter (redirect to the same URL).
 * - `needs_mfa`: send the browser to your sign-in page carrying the attempt
 *   ({@see continuationUrl()}); {@see $cookies} clears the spent Better Auth cookies.
 * - `refused`: carry on unauthenticated; {@see $cookies} clears the Better Auth cookies only when the
 *   refusal is final (invalid, expired, reused, …), so a closed bridge, a 429 or an outage retries later.
 */
final class BridgeDecision
{
    public const NONE = 'none';
    public const SIGNED_IN = 'signed_in';
    public const NEEDS_MFA = 'needs_mfa';
    public const REFUSED = 'refused';

    /**
     * @param list<BridgeCookie> $cookies
     */
    public function __construct(
        public readonly string $action,
        public readonly array $cookies = [],
        public readonly ?ExchangeResult $session = null,
        public readonly string $attemptId = '',
        public readonly string $status = '',
        public readonly ?string $outcome = null,
    ) {
    }

    /** `$url` with `__atlas_attempt` / `__atlas_status` set (for `needs_mfa`). */
    public function continuationUrl(string $url): string
    {
        $fragment = '';
        $hash = strpos($url, '#');
        if ($hash !== false) {
            $fragment = substr($url, $hash);
            $url = substr($url, 0, $hash);
        }
        $query = '';
        $q = strpos($url, '?');
        if ($q !== false) {
            $query = substr($url, $q + 1);
            $url = substr($url, 0, $q);
        }
        $kept = [];
        foreach ($query === '' ? [] : explode('&', $query) as $pair) {
            $key = rawurldecode(explode('=', $pair, 2)[0]);
            if ($key !== '__atlas_attempt' && $key !== '__atlas_status') {
                $kept[] = $pair;
            }
        }
        $kept[] = '__atlas_attempt=' . rawurlencode($this->attemptId);
        $kept[] = '__atlas_status=' . rawurlencode($this->status);

        return $url . '?' . implode('&', $kept) . $fragment;
    }

    /**
     * The `Set-Cookie` header values for {@see $cookies}.
     *
     * @return list<string>
     */
    public function setCookieHeaders(): array
    {
        return array_map(static fn (BridgeCookie $c): string => $c->toHeader(), $this->cookies);
    }
}
