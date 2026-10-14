<?php

declare(strict_types=1);

namespace Atlas\Resources;

/** `/v1/sessions` — mint, list, read and revoke sessions. */
final class Sessions extends Resource
{
    /**
     * `POST /v1/sessions` — mint a session for a user without the sign-in flow.
     * Returns the bearer jwt + refresh token in-body. Refused for a banned user.
     *
     * @param array{user_id:string,actor?:array{sub:string}} $params
     *
     * @return array<string,mixed>
     */
    public function create(array $params): array
    {
        return $this->http->request('POST', '/v1/sessions', body: $params);
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
}
