<?php

declare(strict_types=1);

namespace Atlas\Resources;

/** `/v1/sign_in_tokens` — one-time sign-in tokens for a user. */
/**
 * @deprecated POST /v1/sign_in_tokens is deprecated (Sunset 2026-04-01); use
 * Sessions::create (POST /v1/sessions), which mints a real redeemable session.
 */
final class SignInTokens extends Resource
{
    /**
     * @param array{user_id:string,expires_in_seconds?:int} $body
     *
     * @return array<string,mixed>
     */
    public function create(array $body, ?string $idempotencyKey = null): array
    {
        return $this->http->request('POST', '/v1/sign_in_tokens', body: $body, idempotencyKey: $idempotencyKey);
    }

    /**
     * `POST /v1/sign_in_tokens/:id/revoke` — revoke an unused sign-in token (deprecated surface; prefer
     * sessions.create).
     *
     * @return array<string,mixed>
     */
    public function revoke(string $id): array
    {
        return $this->http->request('POST', '/v1/sign_in_tokens/' . rawurlencode($id) . '/revoke');
    }
}
