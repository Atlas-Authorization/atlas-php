<?php

declare(strict_types=1);

namespace Atlas\Resources;

/**
 * `/v1/instance/hooks` — the `before_user_create` hook. Just before Atlas creates a user from a
 * self-service sign-up, it sends your endpoint a signed `POST` (the webhook headers; verify it
 * with {@see \Atlas\Webhook\Webhook}) of type `user.before_create`, with `data` `{user, strategy, referral}`,
 * and stamps the `external_id` and metadata you answer. A slow or failing hook never blocks a
 * sign-up. `signing_secret` is write-only: reads return `signing_secret_set`.
 */
final class UserHooks extends Resource
{
    /** @return array<string,mixed> {object: before_user_create_hook, enabled, url, signing_secret_set, timeout_ms, last_call, updated_at} */
    public function getBeforeUserCreate(): array
    {
        return $this->http->request('GET', '/v1/instance/hooks/before_user_create');
    }

    /**
     * Partial update; `null` clears `url` / `signing_secret`. Turning it on needs `url` and
     * `signing_secret`.
     *
     * @param array{enabled?:bool,url?:?string,signing_secret?:?string,timeout_ms?:int} $body
     *
     * @return array<string,mixed>
     */
    public function updateBeforeUserCreate(array $body, ?string $idempotencyKey = null): array
    {
        return $this->http->request('PATCH', '/v1/instance/hooks/before_user_create', body: $body, idempotencyKey: $idempotencyKey);
    }

    /**
     * Send the hook a sample draft and return its answer; nothing is stored.
     *
     * @return array<string,mixed> {ok, latency_ms, answer?, error?}
     */
    public function testBeforeUserCreate(): array
    {
        return $this->http->request('POST', '/v1/instance/hooks/before_user_create/test');
    }

    /**
     * The hook's delivery log, newest first (kept 30 days).
     *
     * @param array{limit?:int,status?:string,test?:bool,starting_after?:string} $params
     *
     * @return array<string,mixed>
     */
    public function listBeforeUserCreateCalls(array $params = []): array
    {
        return $this->http->request('GET', '/v1/instance/hooks/before_user_create/calls', query: $params === [] ? null : $params);
    }
}
