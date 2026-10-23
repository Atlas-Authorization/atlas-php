<?php

declare(strict_types=1);

namespace Atlas\BetterAuth;

use Atlas\Client;
use Atlas\Exception\AtlasApiException;
use Atlas\Exception\TransportException;

/**
 * Moving from Better Auth to Atlas without signing anyone out.
 *
 * During the migration window a returning user still carries their Better Auth session cookie
 * (`better-auth.session_token`, `__Secure-` prefixed over https). It is HttpOnly, so only your server
 * can read it. The bridge reads it, exchanges it ONCE with Atlas through the Backend API
 * (`POST /v1/migrations/better_auth/exchange`, secret key, forwarding the end user's IP and user
 * agent), sets the Atlas session as your own first-party cookies and clears the Better Auth cookies.
 * Atlas finds the session in your import (or asks your old app, with the live fallback on) and runs the
 * normal sign-in path on it, MFA policy included: a user who still owes a second factor is sent to
 * finish it.
 *
 * {@see SessionBridge} turns a request's cookies into a {@see BridgeDecision};
 * {@see \Atlas\Psr15\BetterAuthBridgeMiddleware} renders it in a PSR-15 pipeline.
 */
final class BetterAuth
{
    /** Better Auth's default cookie prefix (`advanced.cookiePrefix`). */
    public const COOKIE_PREFIX = 'better-auth';

    /** Refusal outcomes Atlas reports in `meta.bridge_outcome`. */
    public const KNOWN_OUTCOMES = ['closed', 'invalid', 'signature_invalid', 'expired', 'reused', 'user_unavailable', 'rate_limited'];

    /** Refusals that say the Better Auth session will never exchange: its cookies are cleared. */
    public const FINAL_OUTCOMES = ['invalid', 'signature_invalid', 'expired', 'reused', 'user_unavailable'];

    private function __construct()
    {
    }

    /**
     * The Better Auth cookies the bridge reads (`session_token`, the `__Secure-` name first) and
     * clears (`all`).
     *
     * @return array{session_token:list<string>,all:list<string>}
     */
    public static function cookieNames(string $prefix = self::COOKIE_PREFIX): array
    {
        $base = array_map(
            static fn (string $n): string => $prefix . '.' . $n,
            ['session_token', 'session_data', 'account_data', 'dont_remember'],
        );

        return [
            'session_token' => ['__Secure-' . $prefix . '.session_token', $prefix . '.session_token'],
            'all' => array_merge(array_map(static fn (string $n): string => '__Secure-' . $n, $base), $base),
        ];
    }

    /**
     * Parse a `Cookie` header (first occurrence of a name wins).
     *
     * @return array<string,string>
     */
    public static function parseCookieHeader(?string $header): array
    {
        $out = [];
        if ($header === null || $header === '') {
            return $out;
        }
        foreach (explode(';', $header) as $part) {
            $part = trim($part);
            $eq = strpos($part, '=');
            if ($eq === false || $eq === 0) {
                continue;
            }
            $key = substr($part, 0, $eq);
            if (!array_key_exists($key, $out)) {
                $out[$key] = substr($part, $eq + 1);
            }
        }

        return $out;
    }

    /**
     * The raw Better Auth session cookie value from a `Cookie` header (or a parsed cookie map, e.g.
     * `$_COOKIE` or PSR-7 `getCookieParams()`), the `__Secure-` name first; null when absent.
     * `$cookieName` overrides the name when the old app set `advanced.cookies.session_token.name`.
     *
     * @param string|array<string,mixed>|null $cookies
     */
    public static function readSessionCookie(string|array|null $cookies, string $cookiePrefix = self::COOKIE_PREFIX, ?string $cookieName = null): ?string
    {
        $jar = is_array($cookies) ? $cookies : self::parseCookieHeader($cookies);
        $names = $cookieName !== null && $cookieName !== ''
            ? ['__Secure-' . $cookieName, $cookieName]
            : self::cookieNames($cookiePrefix)['session_token'];
        foreach ($names as $name) {
            $value = $jar[$name] ?? null;
            // PHP turns `.` in incoming cookie names into `_` in $_COOKIE; look there too.
            $value ??= $jar[str_replace('.', '_', $name)] ?? null;
            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        return null;
    }

    /**
     * Exchange a Better Auth session (the raw cookie value or the bare token) for an Atlas session over
     * the Backend API. Never throws for a refusal or a transport failure: those are `refused`
     * (`unavailable` when it is not a verdict on the session), so the caller keeps the Better Auth
     * cookie and tries again on a later request. Pass the END USER's `$clientIp` and `$userAgent`.
     */
    public static function exchange(Client $client, string $token, ?string $clientIp = null, ?string $userAgent = null, ?bool $remember = null): ExchangeResult
    {
        try {
            $data = $client->betterAuthBridge->exchange($token, $clientIp, $userAgent, $remember);
        } catch (AtlasApiException $e) {
            return self::classifyError($e);
        } catch (TransportException | \JsonException) {
            return ExchangeResult::refused('unavailable', 0);
        }

        return self::classifySuccess($data);
    }

    /** @param mixed $data */
    public static function classifySuccess(mixed $data): ExchangeResult
    {
        if (is_array($data)) {
            $object = $data['object'] ?? null;
            if ($object === 'session_tokens' && is_string($data['jwt'] ?? null) && $data['jwt'] !== ''
                && is_string($data['refresh_token'] ?? null) && $data['refresh_token'] !== '') {
                return new ExchangeResult(
                    ExchangeResult::SESSION,
                    jwt: $data['jwt'],
                    refreshToken: $data['refresh_token'],
                    sessionId: (string) ($data['session_id'] ?? ''),
                    userId: (string) ($data['user_id'] ?? ''),
                    expiresIn: (int) ($data['expires_in'] ?? 0),
                );
            }
            $status = $data['status'] ?? null;
            if ($object === 'sign_in_attempt' && is_string($data['id'] ?? null) && $data['id'] !== ''
                && in_array($status, ['needs_second_factor', 'needs_mfa_enrollment'], true)) {
                return new ExchangeResult(ExchangeResult::NEEDS_MFA, attemptId: $data['id'], status: $status);
            }
        }

        return ExchangeResult::refused('unavailable', 200);
    }

    public static function classifyError(AtlasApiException $e): ExchangeResult
    {
        $status = $e->getStatus();
        if ($status === 429) {
            return ExchangeResult::refused('rate_limited', 429);
        }
        $meta = ($e->getErrors()[0] ?? null)?->meta;
        $outcome = is_array($meta) ? ($meta['bridge_outcome'] ?? null) : null;
        if (is_string($outcome) && in_array($outcome, self::KNOWN_OUTCOMES, true)) {
            return ExchangeResult::refused($outcome, $status);
        }

        // Anything else (a 5xx, a guard refusal, an unparsable body) is not a verdict on the Better
        // Auth session: keep it and retry later.
        return ExchangeResult::refused('unavailable', $status);
    }
}
