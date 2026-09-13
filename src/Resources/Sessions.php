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
}
