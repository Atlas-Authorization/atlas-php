<?php

declare(strict_types=1);

namespace Atlas\Resources;

/**
 * `/v1/sessions` — mint, list, read and revoke sessions.
 *
 * Every session array (read, list item, touched session, and the items of
 * `users->listSessions()`) carries `idle_expires_at` — epoch ms the session ends
 * from inactivity (the same value as `abandon_at`; absent from older responses) —
 * plus `last_active_organization_id` and `impersonated_by` (string or null).
 */
final class Sessions extends Resource
{
    /**
     * `POST /v1/sessions` — mint a session for a user without the sign-in flow.
     * Returns the bearer jwt + refresh token in-body. Refused for a banned user.
     *
     * `mode: 'browser_ticket'` (with `redirect_url?`, `expires_in_seconds?`) returns a browser ticket
     * instead; {@see createBrowserTicket()} does that for you.
     *
     * @param array{user_id:string,actor?:array{sub:string},mode?:'browser_ticket',redirect_url?:string,expires_in_seconds?:int} $params
     *
     * @return array<string,mixed>
     */
    public function create(array $params): array
    {
        return $this->http->request('POST', '/v1/sessions', body: $params);
    }

    /**
     * `POST /v1/sessions` with `mode: "browser_ticket"` — a one-time ticket that signs a BROWSER in as
     * the user (support handoffs, E2E). No session is minted here; the browser gets one through the
     * normal ticket exchange (`POST /v1/client/sign_ins {strategy: "ticket"}`), or open `url`. The
     * successor of the deprecated {@see SignInTokens::create()}.
     *
     * `$redirectUrl` (one of the instance's allowed origins) is where the ready `url` lands; without it
     * there is no `url`. `$expiresInSeconds`: 1 to 300 (default 60).
     *
     * @return array<string,mixed> `{object: 'browser_ticket', id, user_id, ticket, url, redirect_url, expires_in, expires_at (ISO 8601)}`
     */
    public function createBrowserTicket(string $userId, ?string $redirectUrl = null, ?int $expiresInSeconds = null): array
    {
        return $this->http->request('POST', '/v1/sessions', body: array_filter([
            'user_id' => $userId,
            'redirect_url' => $redirectUrl,
            'expires_in_seconds' => $expiresInSeconds,
            'mode' => 'browser_ticket',
        ], static fn ($v) => $v !== null));
    }

    /**
     * `GET /v1/sessions?user_id=` — requires a user_id.
     *
     * @return array<string,mixed> a ListPage of sessions
     */
    public function list(string $userId): array
    {
        return $this->http->request('GET', '/v1/sessions', query: ['user_id' => $userId]);
    }

    /** @return array<string,mixed> */
    public function get(string $id): array
    {
        return $this->http->request('GET', '/v1/sessions/' . rawurlencode($id));
    }

    /** @return array<string,mixed> */
    public function revoke(string $id): array
    {
        return $this->http->request('POST', '/v1/sessions/' . rawurlencode($id) . '/revoke');
    }

    /**
     * `POST /v1/sessions/:id/touch` — keep a session alive from your backend: slides its inactivity
     * window as if the user had just loaded a page, never past the absolute expiry. Returns the session
     * (with `idle_expires_at`) plus `extended` — false when a touch within the last minute already moved
     * it, so calling it on every request is safe. An ended session throws a 409 (`SESSION_REVOKED`,
     * `SESSION_IDLE_EXPIRED` or `SESSION_EXPIRED`).
     *
     * @return array<string,mixed>
     */
    public function touch(string $id): array
    {
        return $this->http->request('POST', '/v1/sessions/' . rawurlencode($id) . '/touch');
    }

    /**
     * `POST /v1/sessions/:id/tokens/:template` — mint a token from a named JWT template for a session.
     *
     * @return array<string,mixed>
     */
    public function createToken(string $id, string $template): array
    {
        return $this->http->request('POST', '/v1/sessions/' . rawurlencode($id) . '/tokens/' . rawurlencode($template));
    }

    /**
     * `PUT /v1/sessions/:id/custom_data` — replace the session's custom data (null clears
     * it). At most 2 KB; only an active session can be changed. Not for secrets: the
     * signed-in user can read it. Sends the `session.updated` webhook.
     *
     * @param array<string,mixed>|null $customData
     *
     * @return array<string,mixed> the session, with `custom_data`
     */
    public function setCustomData(string $id, ?array $customData): array
    {
        return $this->http->request('PUT', '/v1/sessions/' . rawurlencode($id) . '/custom_data', body: [
            'custom_data' => $customData === [] ? new \stdClass() : $customData,
        ]);
    }

    /**
     * `PATCH /v1/sessions/:id/custom_data` — merge keys into the session's custom data; a
     * key set to null is removed.
     *
     * @param array<string,mixed> $patch
     *
     * @return array<string,mixed>
     */
    public function updateCustomData(string $id, array $patch): array
    {
        return $this->http->request('PATCH', '/v1/sessions/' . rawurlencode($id) . '/custom_data', body: [
            'custom_data' => $patch === [] ? new \stdClass() : $patch,
        ]);
    }

    /**
     * `POST /v1/sessions/:id/integration_tokens/:integration` — an identity-integration token for this session: a preset such as `chatly`, or a custom integration id.
     * `$audience` picks a per-call audience (the integration's `per_call_audience`): `audience` (the whole audience), `cbid`, or the integration's own parameter by name; a value it does not permit is refused (403).
     *
     * @param array<string,string> $audience
     * @return array<string,mixed>
     */
    public function createIntegrationToken(string $id, string $integration, array $audience = []): array
    {
        return $this->http->request('POST', '/v1/sessions/' . rawurlencode($id) . '/integration_tokens/' . rawurlencode($integration), query: $audience === [] ? null : $audience);
    }

    /**
     * `getToken($id, 'template')` mints a JWT-template token ({@see createToken()}); `getToken($id, ['for' => 'chatly'])` mints an integration token ({@see createIntegrationToken()}); a per-call audience goes in the same array: `['for' => 'acme', 'audience' => 'acme:t1']`, `['for' => 'acme', 'cbid' => 's1']` or `['for' => 'acme', 'audienceParams' => ['tenant' => 't1']]`.
     *
     * @return array<string,mixed>
     */
    public function getToken(string $id, string|array $what): array
    {
        return is_array($what)
            ? $this->createIntegrationToken(
                $id,
                (string) ($what['for'] ?? throw new \InvalidArgumentException('getToken() needs [\'for\' => $integration].')),
                self::audienceQuery($what),
            )
            : $this->createToken($id, $what);
    }

    /**
     * @param array<string,mixed> $what
     *
     * @return array<string,string>
     */
    private static function audienceQuery(array $what): array
    {
        $query = [];
        foreach (($what['audienceParams'] ?? []) as $name => $value) {
            $query[(string) $name] = (string) $value;
        }
        foreach (['cbid', 'audience'] as $key) {
            if (isset($what[$key]) && $what[$key] !== '') {
                $query[$key] = (string) $what[$key];
            }
        }

        return $query;
    }
}
