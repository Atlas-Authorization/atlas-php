<?php

declare(strict_types=1);

namespace Atlas\Resources;

/** `/v1/actor_tokens` — impersonation actor tokens (one-time secret). */
final class ActorTokens extends Resource
{
    /**
     * @param array{user_id:string,actor:array{sub:string},expires_in_seconds?:int} $body
     *
     * @return array<string,mixed>
     */
    public function create(array $body, ?string $idempotencyKey = null): array
    {
        return $this->http->request('POST', '/v1/actor_tokens', body: $body, idempotencyKey: $idempotencyKey);
    }

    /** @return array<string,mixed> */
    public function revoke(string $id): array
    {
        return $this->http->request('POST', '/v1/actor_tokens/' . rawurlencode($id) . '/revoke');
    }
}
