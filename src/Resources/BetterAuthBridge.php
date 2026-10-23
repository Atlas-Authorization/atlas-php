<?php

declare(strict_types=1);

namespace Atlas\Resources;

/**
 * `/v1/migrations/better_auth/*` — the Better Auth session bridge. During a migration window a
 * returning user's Better Auth session is exchanged, once, for an Atlas session, so nobody is signed
 * out by the switch. See {@see \Atlas\BetterAuth\BetterAuth::exchange()} for a classified result and
 * {@see \Atlas\Psr15\BetterAuthBridgeMiddleware} for the request-level bridge.
 */
final class BetterAuthBridge extends Resource
{
    /** The Backend API path of the secret-key exchange. */
    public const EXCHANGE_PATH = '/v1/migrations/better_auth/exchange';

    /**
     * `GET /v1/migrations/better_auth/bridge` — the bridge settings and exchange counts
     * (`live_fallback.has_secret`, never the secret).
     *
     * @return array<string,mixed>
     */
    public function get(): array
    {
        return $this->http->request('GET', '/v1/migrations/better_auth/bridge');
    }

    /**
     * `PUT /v1/migrations/better_auth/bridge` — create or update the settings, merged onto the stored ones:
     * `enabled`, `ends_at` (ISO-8601 or Unix ms; null = open), `cookie_prefix`, `live_fallback`
     * (`enabled`, `base_url`, `secret` — write-only: omit to keep, null to clear).
     *
     * @param array<string,mixed> $body
     *
     * @return array<string,mixed>
     */
    public function update(array $body, ?string $idempotencyKey = null): array
    {
        return $this->http->request('PUT', '/v1/migrations/better_auth/bridge', body: $body, idempotencyKey: $idempotencyKey);
    }

    /**
     * `DELETE /v1/migrations/better_auth/bridge` — remove the settings: the bridge is off and the stored
     * secret is deleted.
     *
     * @return array<string,mixed>
     */
    public function delete(?string $idempotencyKey = null): array
    {
        return $this->http->request('DELETE', '/v1/migrations/better_auth/bridge', idempotencyKey: $idempotencyKey);
    }

    /**
     * `POST /v1/migrations/better_auth/exchange` — exchange a Better Auth session (the raw cookie value or
     * the bare token) for an Atlas session, once. Returns `session_tokens` (`jwt`, `refresh_token`,
     * `session_id`, `user_id`, `expires_in`) or, when a second factor is owed, a `sign_in_attempt`
     * (`id`, `status`).
     *
     * `$clientIp` / `$userAgent` are the END USER's, forwarded (`X-Atlas-Client-IP`,
     * `X-Atlas-Client-User-Agent`) so Atlas's guards, risk and geo checks and the device record see the
     * user rather than your server. Throws {@see \Atlas\Exception\AtlasApiException} on a refusal
     * (`meta.bridge_outcome`); {@see \Atlas\BetterAuth\BetterAuth::exchange()} classifies it instead.
     *
     * @return array<string,mixed>
     */
    public function exchange(string $token, ?string $clientIp = null, ?string $userAgent = null, ?bool $remember = null): array
    {
        $headers = [];
        if ($clientIp !== null && $clientIp !== '') {
            $headers['X-Atlas-Client-IP'] = $clientIp;
        }
        if ($userAgent !== null && $userAgent !== '') {
            $headers['X-Atlas-Client-User-Agent'] = $userAgent;
        }
        $body = ['token' => $token];
        if ($remember === false) {
            $body['remember'] = false;
        }

        return $this->http->request('POST', self::EXCHANGE_PATH, body: $body, headers: $headers);
    }
}
