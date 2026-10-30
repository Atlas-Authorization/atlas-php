<?php

declare(strict_types=1);

namespace Atlas\Resources;

/**
 * `/v1/sign_in_tokens` — one-time sign-in tokens for a user.
 *
 * @deprecated The sign-in token API is deprecated (sunset 2027-04-01). Use
 * {@see Sessions::createBrowserTicket()}: the same one-time ticket, plus a ready URL.
 */
final class SignInTokens extends Resource
{
    /**
     * @deprecated The sign-in token API is deprecated (sunset 2027-04-01). Use
     * {@see Sessions::createBrowserTicket()}: the same one-time ticket, plus a ready URL.
     *
     * @param array{user_id:string,expires_in_seconds?:int} $body
     *
     * @return array<string,mixed>
     */
    public function create(array $body, ?string $idempotencyKey = null): array
    {
        return $this->http->request('POST', '/v1/sign_in_tokens', body: $body, idempotencyKey: $idempotencyKey);
    }

    /**
     * `POST /v1/sign_in_tokens/:id/revoke` — revoke an unused sign-in token before its 60-second window
     * elapses.
     *
     * @deprecated The sign-in token API is deprecated (sunset 2027-04-01). Use
     * {@see Sessions::createBrowserTicket()} (or {@see Sessions::create()} for a server-side session).
     *
     * @return array<string,mixed>
     */
    public function revoke(string $id): array
    {
        return $this->http->request('POST', '/v1/sign_in_tokens/' . rawurlencode($id) . '/revoke');
    }
}
